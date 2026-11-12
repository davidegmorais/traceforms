<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSession;
use App\Models\Submission;
use App\Models\Upload;
use App\Notifications\NewSubmission;
use App\Services\Tracking\SessionRecorder;
use App\Support\BrDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmissionService
{
    public function __construct(private SessionRecorder $recorder) {}

    /**
     * Regras de validação do campo. Retorna [chave => regras], pois alguns tipos
     * (endereço) validam subcampos.
     */
    public function rules(FormField $field, Form $form, ?FormSession $session = null): array
    {
        $k = $field->key;
        $base = [$field->required ? 'required' : 'nullable'];
        $min = fn ($d = null) => ($v = $field->setting('min', $d)) !== null && $v !== '' ? 'min:'.$v : null;
        $max = fn ($d = null) => ($v = $field->setting('max', $d)) !== null && $v !== '' ? 'max:'.$v : null;

        $rules = match ($field->type) {
            'short_text' => ['string', 'max:'.((int) $field->setting('max_length') ?: 500)],
            'long_text' => ['string', 'max:'.((int) $field->setting('max_length') ?: 10000)],
            'email' => ['email:rfc', 'max:255'],
            'phone' => ['string', 'max:40', 'regex:/^[0-9+()\-\s.]{6,40}$/'],
            'url' => ['url', 'max:2000'],
            'number' => array_filter(['numeric', $min(), $max()]),
            'currency' => array_filter(['numeric', $min(0), $max()]),
            'slider' => ['numeric', 'between:'.(float) $field->setting('min', 0).','.(float) $field->setting('max', 100)],
            'date' => ['date'],
            'time' => ['date_format:H:i'],
            'cpf_cnpj' => ['string', function ($a, $v, $fail) use ($field) {
                $doc = $field->setting('doc', 'both');
                $ok = match ($doc) {
                    'cpf' => BrDocs::isCpf($v),
                    'cnpj' => BrDocs::isCnpj($v),
                    default => BrDocs::isCpf($v) || BrDocs::isCnpj($v),
                };
                if (! $ok) {
                    $fail(['cpf' => 'Informe um CPF válido.', 'cnpj' => 'Informe um CNPJ válido.'][$doc] ?? 'Informe um CPF ou CNPJ válido.');
                }
            }],
            'radio', 'select' => ['string', function ($a, $v, $fail) use ($field) {
                if (! in_array($v, $field->options ?? [], true) && ! ($field->setting('allow_other') && mb_strlen($v) <= 255)) {
                    $fail('Selecione uma opção válida.');
                }
            }],
            'checkbox' => ['array', function ($a, $value, $fail) use ($field) {
                foreach ((array) $value as $v) {
                    if (! is_string($v) || (! in_array($v, $field->options ?? [], true) && ! $field->setting('allow_other'))) {
                        $fail('Selecione opções válidas.');
                    }
                }
                if (($m = (int) $field->setting('max_choices')) > 0 && count((array) $value) > $m) {
                    $fail("Selecione no máximo {$m} opções.");
                }
            }],
            'ranking' => ['array', function ($a, $value, $fail) use ($field) {
                $value = (array) $value;
                $opts = $field->options ?? [];
                if (array_diff($value, $opts) || count($value) !== count(array_unique($value))) {
                    $fail('Ordem inválida.');
                }
                if ($field->required && count($value) !== count($opts)) {
                    $fail('Ordene todas as opções.');
                }
            }],
            'matrix' => ['array', function ($a, $value, $fail) use ($field) {
                $rows = $field->matrixRows();
                foreach ((array) $value as $r => $c) {
                    if (! in_array($r, $rows, true) || ! in_array($c, $field->options ?? [], true)) {
                        $fail('Resposta inválida na grade.');

                        return;
                    }
                }
                if ($field->required && count(array_filter((array) $value)) < count($rows)) {
                    $fail('Responda todas as linhas.');
                }
            }],
            'yes_no' => ['in:Sim,Não'],
            'rating' => ['integer', 'between:1,'.((int) $field->setting('max', 5))],
            'nps' => ['integer', 'between:0,10'],
            'scale' => ['integer', 'between:'.((int) $field->setting('min', 1)).','.((int) $field->setting('max', 5))],
            'consent' => $field->required ? ['accepted'] : ['nullable'],
            'file' => ['array', 'max:'.max(1, (int) $field->setting('max_files', 1)), function ($a, $value, $fail) use ($field, $form, $session) {
                $ids = array_filter((array) $value, 'is_string');
                $found = Upload::where('form_id', $form->id)->where('field_key', $field->key)->whereNull('submission_id')
                    ->when($session, fn ($q) => $q->where(fn ($w) => $w->whereNull('form_session_id')->orWhere('form_session_id', $session->id)))
                    ->whereIn('uuid', $ids)->count();
                if ($found !== count($ids) || count($ids) !== count((array) $value)) {
                    $fail('Envie o arquivo novamente.');
                }
            }],
            'signature' => ['string', 'max:700000', 'starts_with:data:image/png;base64,'],
            default => ['nullable'],
        };

        $out = [$k => array_merge($base, $rules)];

        if ($field->type === 'address') {
            $req = $field->required ? 'required' : 'nullable';
            $out = [
                $k => [$field->required ? 'required' : 'nullable', 'array'],
                "$k.cep" => [$req, 'regex:/^\d{5}-?\d{3}$/'],
                "$k.street" => [$req, 'string', 'max:200'],
                "$k.number" => [$field->required && ! $field->setting('number_optional') ? 'required' : 'nullable', 'string', 'max:20'],
                "$k.complement" => ['nullable', 'string', 'max:100'],
                "$k.district" => ['nullable', 'string', 'max:120'],
                "$k.city" => [$req, 'string', 'max:120'],
                "$k.state" => [$req, 'string', 'size:2'],
            ];
        }

        return $out;
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'accepted' => 'É necessário aceitar para continuar.',
            'email' => 'Informe um e-mail válido.',
            'url' => 'Informe uma URL válida (com https://).',
            'numeric' => 'Informe um número.',
            'integer' => 'Selecione um valor.',
            'between' => 'Escolha um valor entre :min e :max.',
            'min' => 'O valor mínimo é :min.',
            'max' => 'Máximo de :max.',
            'date' => 'Informe uma data válida.',
            'date_format' => 'Informe um horário válido.',
            'regex' => 'Formato inválido.',
            'in' => 'Selecione uma opção.',
            'size' => 'Use a sigla com 2 letras.',
            'starts_with' => 'Assinatura inválida. Assine novamente.',
            'array' => 'Resposta inválida.',
        ];
    }

    /**
     * Valida e grava a resposta.
     *
     * @throws ValidationException
     */
    public function submit(Form $form, Request $request): Submission
    {
        $answers = (array) $request->input('answers', []);
        $visible = FormLogic::visibleFields($form, $answers);

        $session = $request->filled('sid')
            ? FormSession::where('uuid', $request->input('sid'))->where('form_id', $form->id)->first()
            : null;

        $rules = [];
        foreach ($visible as $field) {
            if (! in_array($field->type, ['statement', 'hidden'], true)) {
                $rules += $this->rules($field, $form, $session);
            }
        }

        $validator = Validator::make($answers, $rules, $this->messages());
        if ($validator->fails()) {
            // Erros de subcampos (endereço.cep) são reportados no campo principal.
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $msgs) {
                $errors[explode('.', $key)[0]] ??= $msgs;
            }
            throw ValidationException::withMessages($errors);
        }
        $validated = $validator->validated();

        [$isSpam, $reason] = $this->spamCheck($form, $request, $session);

        return DB::transaction(function () use ($form, $request, $answers, $visible, $validated, $session, $isSpam, $reason) {
            $submission = Submission::create([
                'uuid' => (string) Str::uuid(),
                'form_id' => $form->id,
                'form_session_id' => $session?->id,
                'data' => [],
                'is_spam' => $isSpam,
                'spam_reason' => $reason,
                'submitted_at' => now(),
            ]);

            $data = [];
            foreach ($form->fields as $field) {
                if ($field->type === 'statement') {
                    continue;
                }
                if ($field->type === 'hidden') {
                    $data[$field->key] = $session?->hidden_fields[$field->key] ?? $answers[$field->key] ?? null;

                    continue;
                }
                if (! isset($visible[$field->key])) {
                    continue;
                }
                $data[$field->key] = $this->normalize($field, $validated[$field->key] ?? null, $form, $submission, $session);
            }
            $submission->update(['data' => $data]);

            if ($session) {
                $this->recorder->progress($session, (array) $request->input('meta', []) + ['started' => true]);
                $session->refresh();
                $session->update([
                    // Envios marcados como spam não contam como conversão nos relatórios.
                    'status' => $isSpam ? 'spam' : 'submitted',
                    'submitted_at' => now(),
                    'started_at' => $session->started_at ?? now(),
                    'partial_data' => null,
                    'max_step' => max($session->max_step, $form->fields->count()),
                ]);
            }

            if (! $isSpam) {
                DB::afterCommit(fn () => $this->dispatchSideEffects($form, $submission));
            }

            return $submission;
        });
    }

    /** Converte o valor validado para o formato gravado na resposta. */
    private function normalize(FormField $field, mixed $v, Form $form, Submission $submission, ?FormSession $session): mixed
    {
        if ($v === null || $v === '' || $v === []) {
            return $field->type === 'consent' ? null : ($v === [] ? null : $v);
        }

        return match ($field->type) {
            'consent' => 'Aceito',
            'rating', 'nps', 'scale' => (int) $v,
            'number', 'slider' => $v + 0,
            'currency' => round((float) $v, 2),
            'cpf_cnpj' => BrDocs::format($v),
            'address' => array_filter([
                'cep' => BrDocs::format($v['cep'] ?? ''), 'street' => $v['street'] ?? null, 'number' => $v['number'] ?? null,
                'complement' => $v['complement'] ?? null, 'district' => $v['district'] ?? null, 'city' => $v['city'] ?? null,
                'state' => isset($v['state']) ? mb_strtoupper($v['state']) : null,
            ], fn ($x) => $x !== null && $x !== ''),
            'matrix' => array_filter((array) $v, fn ($x) => $x !== null && $x !== ''),
            'ranking' => array_values((array) $v),
            'file' => $this->attachUploads($field, (array) $v, $form, $submission),
            'signature' => [$this->storeSignature($field, $v, $form, $submission, $session)->toAnswer()],
            default => $v,
        };
    }

    private function attachUploads(FormField $field, array $ids, Form $form, Submission $submission): array
    {
        $uploads = Upload::where('form_id', $form->id)->where('field_key', $field->key)->whereNull('submission_id')->whereIn('uuid', $ids)->get();
        $uploads->each->update(['submission_id' => $submission->id]);

        return $uploads->map->toAnswer()->values()->all();
    }

    private function storeSignature(FormField $field, string $dataUrl, Form $form, Submission $submission, ?FormSession $session): Upload
    {
        $png = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages([$field->key => 'Assinatura inválida. Assine novamente.']);
        }
        $uuid = (string) Str::uuid();
        $path = "uploads/{$form->id}/{$uuid}.png";
        Storage::disk('local')->put($path, $png);

        return Upload::create([
            'uuid' => $uuid, 'form_id' => $form->id, 'form_session_id' => $session?->id, 'submission_id' => $submission->id,
            'field_key' => $field->key, 'original_name' => 'assinatura.png', 'mime' => 'image/png', 'size' => strlen($png),
            'disk' => 'local', 'path' => $path,
        ]);
    }

    private function spamCheck(Form $form, Request $request, ?FormSession $session): array
    {
        if ($request->filled('website_url')) { // honeypot
            return [true, 'honeypot'];
        }
        $min = (int) $form->setting('min_submit_seconds', 3);
        $elapsed = (int) $request->input('meta.duration_ms', 0) / 1000;
        if ($min > 0 && $elapsed > 0 && $elapsed < $min) {
            return [true, 'rápido demais ('.round($elapsed, 1).'s)'];
        }
        if ($session?->is_bot) {
            return [true, 'bot detectado'];
        }

        return [false, null];
    }

    private function dispatchSideEffects(Form $form, Submission $submission): void
    {
        $emails = array_filter((array) $form->setting('notify_emails', []), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($emails) {
            try {
                Notification::route('mail', $emails)->notify(new NewSubmission($form, $submission));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($url = $form->setting('webhook_url')) {
            DeliverWebhook::dispatch($submission->id, $url)->afterResponse();
        }
    }
}
