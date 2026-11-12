<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSession;
use App\Models\Membership;
use App\Models\TrackingLink;
use App\Services\FormLogic;
use App\Services\SubmissionService;
use App\Services\Tracking\SessionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicFormController extends Controller
{
    public function show(Request $request, string $publicId)
    {
        $form = Form::with('fields', 'account')->where('public_id', $publicId)->firstOrFail();

        // Pré-visualização: rascunhos podem ser vistos por membros da conta, sem gerar dados.
        $preview = $request->boolean('preview') && $request->user()
            && Membership::where('account_id', $form->account_id)->where('user_id', $request->user()->id)->exists();

        $closed = $preview ? null : $form->closedReason();

        if (! $closed && ! $preview && $form->setting('one_per_visitor') && ($vid = $request->cookie('tf_vid'))) {
            if (FormSession::where('form_id', $form->id)->where('visitor_id', $vid)->where('status', 'submitted')->exists()) {
                $closed = 'already';
            }
        }

        return response()
            ->view('public.form', [
                'form' => $form,
                'preview' => $preview,
                'closed' => $closed,
                'embedded' => $request->boolean('embed'),
                'transparent' => $request->boolean('transparent'),
                'fieldsJson' => $form->fields->map->toBuilderArray()->values(),
                'operators' => FormLogic::OPERATORS,
            ])
            ->header('Content-Security-Policy', 'frame-ancestors *')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** Endpoint de coleta (view, start, progress). */
    public function collect(Request $request, string $publicId, SessionRecorder $recorder): JsonResponse
    {
        $form = Form::with('fields')->where('public_id', $publicId)->firstOrFail();
        $payload = $request->json()->all() ?: $request->all();
        $type = $payload['type'] ?? null;

        if ($type === 'view') {
            if ($form->status === 'draft') {
                return response()->json(['ok' => false], 202);
            }
            $session = $recorder->start($form, $request, $payload);

            return response()->json(['sid' => $session->uuid]);
        }

        $session = FormSession::where('uuid', (string) ($payload['sid'] ?? ''))->where('form_id', $form->id)->first();
        if ($session) {
            $session->setRelation('form', $form);
            $recorder->progress($session, $payload);
        }

        return response()->json(['ok' => (bool) $session]);
    }

    public function submit(Request $request, string $publicId, SubmissionService $service): JsonResponse
    {
        $form = Form::with('fields')->where('public_id', $publicId)->firstOrFail();

        if ($reason = $form->closedReason()) {
            return response()->json(['message' => $form->setting('closed_message'), 'reason' => $reason], 423);
        }

        try {
            $submission = $service->submit($form, $request);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Verifique os campos destacados.', 'errors' => $e->errors()], 422);
        }

        $redirect = $form->setting('redirect_url');
        if ($redirect && $form->setting('redirect_pass_params') && $submission->session) {
            $s = $submission->session;
            $extra = array_filter([
                'utm_source' => $s->utm_source, 'utm_medium' => $s->utm_medium, 'utm_campaign' => $s->utm_campaign,
                'utm_term' => $s->utm_term, 'utm_content' => $s->utm_content, 'response_id' => $submission->uuid,
            ]);
            $redirect .= (str_contains($redirect, '?') ? '&' : '?').http_build_query($extra);
        }

        return response()->json([
            'ok' => true,
            'id' => $submission->uuid,
            'redirect' => $redirect,
        ]);
    }

    /** Recebe um arquivo de um campo "Envio de arquivo" antes do envio da resposta. */
    public function upload(Request $request, string $publicId): JsonResponse
    {
        $form = Form::with('fields')->where('public_id', $publicId)->firstOrFail();
        if ($form->closedReason() && ! $request->boolean('preview')) {
            return response()->json(['message' => 'Formulário encerrado.'], 423);
        }
        $field = $form->fields->firstWhere('key', (string) $request->input('field'));
        if (! $field || $field->type !== 'file') {
            return response()->json(['message' => 'Campo inválido.'], 422);
        }

        $maxKb = min((int) config('traceforms.upload_max_mb', 25), max(1, (int) $field->setting('max_size', 10))) * 1024;
        $mimes = (\App\Support\FieldTypes::ACCEPT[$field->setting('accept', 'any')] ?? \App\Support\FieldTypes::ACCEPT['any'])['mimes'];
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), ['file' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$mimes]], [
            'file.max' => 'Arquivo muito grande. O limite é '.($maxKb / 1024).' MB.',
            'file.mimes' => 'Tipo de arquivo não aceito. Envie: '.str_replace(',', ', ', $mimes).'.',
            'file.uploaded' => 'Não foi possível receber o arquivo. Tente novamente.',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first()], 422);
        }

        $session = $request->filled('sid') ? FormSession::where('uuid', $request->input('sid'))->where('form_id', $form->id)->first() : null;
        if ($session && \App\Models\Upload::where('form_session_id', $session->id)->count() >= 30) {
            return response()->json(['message' => 'Limite de arquivos atingido.'], 429);
        }

        $file = $request->file('file');
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs('uploads/'.$form->id, $uuid.'.'.$ext, 'local');

        $upload = \App\Models\Upload::create([
            'uuid' => $uuid, 'form_id' => $form->id, 'form_session_id' => $session?->id, 'field_key' => $field->key,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200), 'mime' => $file->getMimeType(),
            'size' => $file->getSize(), 'disk' => 'local', 'path' => $path,
        ]);

        return response()->json($upload->toAnswer());
    }

    /** Link curto rastreado: conta o clique e redireciona com as UTMs. */
    public function link(string $code)
    {
        $link = TrackingLink::with('form')->where('code', $code)->firstOrFail();
        $link->increment('clicks');

        $params = $link->utmParams() + ['tf_link' => $link->code];
        foreach (request()->query() as $k => $v) {
            if (is_scalar($v) && ! isset($params[$k])) {
                $params[$k] = $v;
            }
        }

        return redirect()->away($link->form->publicUrl($params));
    }
}
