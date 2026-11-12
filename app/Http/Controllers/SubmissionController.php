<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormSession;
use App\Models\Submission;
use App\Services\Reports\ReportService;
use App\Services\Tracking\ChannelClassifier;
use App\Services\Tracking\TrafficParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    private function query(Request $request, Form $form): Builder
    {
        $q = $form->submissions()->with('session')->getQuery();

        $view = $request->query('view', 'inbox');
        match ($view) {
            'starred' => $q->where('starred', true)->where('is_spam', false),
            'spam' => $q->where('is_spam', true),
            'all' => null,
            default => $q->where('is_spam', false),
        };

        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('data', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%')->orWhere('notes', 'like', "%{$s}%")->orWhere('uuid', $s));
        }
        if ($from = $request->query('from')) {
            $q->where('submitted_at', '>=', \Carbon\Carbon::parse($from, $this->account()->timezone)->startOfDay()->utc());
        }
        if ($to = $request->query('to')) {
            $q->where('submitted_at', '<=', \Carbon\Carbon::parse($to, $this->account()->timezone)->endOfDay()->utc());
        }
        $seg = array_filter($request->only(['channel', 'utm_source', 'utm_medium', 'utm_campaign', 'device_type', 'country']), fn ($v) => is_string($v) && $v !== '');
        if ($seg) {
            $q->whereHas('session', function ($s) use ($seg) {
                foreach ($seg as $k => $v) {
                    $s->where($k, $v);
                }
            });
        }

        return $q->orderByDesc('submitted_at');
    }

    public function index(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'responses.view');
        $form->load('fields');

        $columns = $form->fields->filter(fn ($f) => $f->type !== 'statement')->take(4);

        return view('submissions.index', [
            'form' => $form,
            'submissions' => $this->query($request, $form)->paginate(25)->withQueryString(),
            'columns' => $columns,
            'counts' => [
                'inbox' => $form->submissions()->where('is_spam', false)->count(),
                'starred' => $form->submissions()->where('starred', true)->where('is_spam', false)->count(),
                'spam' => $form->submissions()->where('is_spam', true)->count(),
                'abandoned' => $form->sessions()->where('status', 'started')->whereNotNull('partial_data')->count(),
            ],
            'options' => [
                'channel' => FormSession::where('form_id', $form->id)->whereNotNull('channel')->distinct()->pluck('channel')->mapWithKeys(fn ($c) => [$c => ChannelClassifier::label($c)]),
                'utm_campaign' => FormSession::where('form_id', $form->id)->where('status', 'submitted')->whereNotNull('utm_campaign')->distinct()->limit(100)->pluck('utm_campaign'),
                'utm_source' => FormSession::where('form_id', $form->id)->where('status', 'submitted')->whereNotNull('utm_source')->distinct()->limit(100)->pluck('utm_source'),
                'device_type' => FormSession::where('form_id', $form->id)->whereNotNull('device_type')->distinct()->pluck('device_type'),
            ],
        ]);
    }

    public function show(Form $form, Submission $submission)
    {
        $this->authorizeForm($form, 'responses.view');
        abort_unless($submission->form_id === $form->id, 404);
        $form->load('fields');
        $submission->load('session.trackingLink');

        $siblings = $form->submissions()->where('is_spam', $submission->is_spam);
        $journey = $submission->session
            ? FormSession::where('form_id', $form->id)->where('visitor_id', $submission->session->visitor_id)->orderBy('created_at')->limit(30)->get()
            : collect();

        return view('submissions.show', [
            'form' => $form,
            'submission' => $submission,
            's' => $submission->session,
            'journey' => $journey,
            'prev' => (clone $siblings)->where('submitted_at', '>', $submission->submitted_at)->orderBy('submitted_at')->value('uuid'),
            'next' => (clone $siblings)->where('submitted_at', '<', $submission->submitted_at)->orderByDesc('submitted_at')->value('uuid'),
        ]);
    }

    public function update(Request $request, Form $form, Submission $submission)
    {
        $this->authorizeForm($form, 'responses.manage');
        abort_unless($submission->form_id === $form->id, 404);
        $data = $request->validate([
            'starred' => ['sometimes', 'boolean'],
            'is_spam' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $submission->update($data + (isset($data['is_spam']) ? ['spam_reason' => $data['is_spam'] ? 'marcado manualmente' : null] : []));
        if (isset($data['is_spam']) && $submission->session) {
            $submission->session->update(['status' => $data['is_spam'] ? 'spam' : 'submitted']);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Resposta atualizada.');
    }

    public function destroy(Form $form, Submission $submission)
    {
        $this->authorizeForm($form, 'responses.manage');
        abort_unless($submission->form_id === $form->id, 404);
        $submission->session?->update(['status' => 'started']);
        $submission->uploads->each->delete();
        $submission->delete();
        AuditLog::record('submission.deleted', 'Excluiu uma resposta de "'.$form->title.'"', $form);

        return redirect()->route('submissions.index', $form)->with('success', 'Resposta excluída.');
    }

    /** Baixa (ou exibe, se for imagem) um arquivo enviado. */
    public function file(Request $request, Form $form, \App\Models\Upload $upload)
    {
        $this->authorizeForm($form, 'responses.view');
        abort_unless($upload->form_id === $form->id && $upload->submission_id, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk($upload->disk);
        abort_unless($disk->exists($upload->path), 404);

        return $request->boolean('download') || ! $upload->isImage()
            ? $disk->download($upload->path, $upload->original_name)
            : response()->file($disk->path($upload->path), ['Content-Type' => $upload->mime, 'Cache-Control' => 'private, max-age=3600']);
    }

    /** Sessões que começaram a preencher e desistiram (respostas parciais). */
    public function abandoned(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'responses.view');
        $form->load('fields');

        return view('submissions.abandoned', [
            'form' => $form,
            'sessions' => $form->sessions()->where('status', 'started')->whereNotNull('partial_data')
                ->where('is_bot', false)->latest('last_activity_at')->paginate(25),
        ]);
    }

    /** Exporta CSV (UTF-8 com BOM para abrir corretamente no Excel) com respostas + rastreamento. */
    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->authorizeForm($form, 'responses.export');
        $form->load('fields');
        $fields = $form->fields->filter(fn ($f) => $f->type !== 'statement')->values();
        $tz = $this->account()->timezone;
        $query = $this->query($request, $form);
        AuditLog::record('export.csv', 'Exportou respostas de "'.$form->title.'"', $form);

        $tracking = [
            'channel' => 'Canal', 'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign',
            'utm_term' => 'utm_term', 'utm_content' => 'utm_content', 'utm_id' => 'utm_id', 'click_id_type' => 'Tipo click ID', 'click_id' => 'Click ID',
            'ft_source' => '1º toque: origem', 'ft_medium' => '1º toque: mídia', 'ft_campaign' => '1º toque: campanha',
            'referrer' => 'Referrer', 'landing_url' => 'URL de entrada', 'parent_url' => 'Página incorporadora',
            'device_type' => 'Dispositivo', 'device_brand' => 'Marca', 'device_model' => 'Modelo', 'os' => 'Sistema', 'browser' => 'Navegador',
            'screen' => 'Tela', 'language' => 'Idioma', 'timezone' => 'Fuso', 'country' => 'País', 'region' => 'Região', 'city' => 'Cidade',
            'ip' => 'IP', 'visitor_id' => 'ID visitante', 'visit_number' => 'Nº da visita', 'duration' => 'Tempo de preenchimento (s)',
        ];

        return response()->streamDownload(function () use ($query, $fields, $tz, $tracking) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['ID', 'Enviado em'], $fields->pluck('label')->all(), array_values($tracking), ['Spam', 'Favorito', 'Notas']), ';');
            $query->chunk(1000, function ($rows) use ($out, $fields, $tz, $tracking) {
                foreach ($rows as $sub) {
                    $s = $sub->session;
                    $t = [
                        'channel' => ChannelClassifier::label($s?->channel), 'utm_source' => $s?->utm_source, 'utm_medium' => $s?->utm_medium,
                        'utm_campaign' => $s?->utm_campaign, 'utm_term' => $s?->utm_term, 'utm_content' => $s?->utm_content, 'utm_id' => $s?->utm_id,
                        'click_id_type' => TrafficParser::clickLabel($s?->click_id_type), 'click_id' => $s?->click_id,
                        'ft_source' => $s?->first_touch['utm_source'] ?? null, 'ft_medium' => $s?->first_touch['utm_medium'] ?? null,
                        'ft_campaign' => $s?->first_touch['utm_campaign'] ?? null, 'referrer' => $s?->referrer, 'landing_url' => $s?->landing_url,
                        'parent_url' => $s?->parent_url, 'device_type' => $s?->device_type, 'device_brand' => $s?->device_brand,
                        'device_model' => $s?->device_model, 'os' => trim(($s?->os_name ?? '').' '.($s?->os_version ?? '')),
                        'browser' => trim(($s?->browser_name ?? '').' '.($s?->browser_version ?? '')), 'screen' => $s?->screenLabel(),
                        'language' => $s?->language, 'timezone' => $s?->timezone, 'country' => $s?->country, 'region' => $s?->region,
                        'city' => $s?->city, 'ip' => $s?->ip, 'visitor_id' => $s?->visitor_id, 'visit_number' => $s?->visit_number,
                        'duration' => $s?->duration_ms ? round($s->duration_ms / 1000) : null,
                    ];
                    fputcsv($out, array_merge(
                        [$sub->uuid, $sub->submitted_at->setTimezone($tz)->format('d/m/Y H:i:s')],
                        $fields->map(fn ($f) => is_array($v = $sub->data[$f->key] ?? null) || in_array($f->type, ['currency', 'cpf_cnpj', 'slider'], true) ? ($v === null ? null : $f->display($v)) : $v)->all(),
                        array_map(fn ($k) => $t[$k], array_keys($tracking)),
                        [$sub->is_spam ? 'Sim' : 'Não', $sub->starred ? 'Sim' : 'Não', $sub->notes]
                    ), ';');
                }
            });
            fclose($out);
        }, 'respostas-'.\Illuminate\Support\Str::slug($form->title).'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
