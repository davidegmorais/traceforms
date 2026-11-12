<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Services\FormLogic;
use App\Support\FieldTypes;
use App\Support\FormTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    /** Fontes disponíveis no tema do formulário (Instrument Sans é servida localmente; as demais via Google Fonts). */
    public const FONTS = ['Instrument Sans', 'Bricolage Grotesque', 'Inter', 'Poppins', 'Roboto', 'Montserrat', 'Lato', 'Nunito', 'DM Sans', 'Manrope', 'Lora', 'Fraunces', 'Playfair Display'];

    public function index(Request $request)
    {
        $q = $this->visibleForms()
            ->withCount([
                'sessions as views_count' => fn ($q) => $q->where('is_bot', false),
                'sessions as starts_count' => fn ($q) => $q->where('is_bot', false)->whereNotNull('started_at'),
                'submissions as responses_count' => fn ($q) => $q->where('is_spam', false),
            ])
            ->withMax('submissions as last_response_at', 'submitted_at')
            ->with('creator:id,name');

        if ($search = trim((string) $request->query('q'))) {
            $q->where('title', 'like', "%{$search}%");
        }
        if (in_array($status = $request->query('status'), ['draft', 'published', 'closed'], true)) {
            $q->where('status', $status);
        }
        $sort = $request->query('sort', 'updated');
        match ($sort) {
            'responses' => $q->orderByDesc('responses_count'),
            'title' => $q->orderBy('title'),
            default => $q->latest('updated_at'),
        };

        return view('forms.index', [
            'forms' => $q->paginate(18)->withQueryString(),
            'counts' => $this->visibleForms()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create()
    {
        return view('forms.create', ['templates' => FormTemplates::all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'template' => ['nullable', Rule::in(array_keys(FormTemplates::all()))],
            'layout' => ['nullable', Rule::in(Form::LAYOUTS)],
        ]);
        $tpl = FormTemplates::all()[$data['template'] ?? 'blank'];
        $tpl['layout'] = $data['layout'] ?? $tpl['layout'];
        $data['title'] = trim((string) ($data['title'] ?? '')) ?: (($data['template'] ?? 'blank') === 'blank' ? 'Novo formulário' : $tpl['name']);

        $form = DB::transaction(function () use ($data, $tpl, $request) {
            $form = $this->account()->forms()->create([
                'title' => $data['title'],
                'created_by' => $request->user()->id,
                'layout' => $tpl['layout'],
                'theme' => ['primary' => $this->account()->brand_color],
            ]);
            foreach (FormTemplates::fields($data['template'] ?? 'blank') as $f) {
                $form->fields()->create($f);
            }

            return $form;
        });
        AuditLog::record('form.created', 'Criou o formulário "'.$form->title.'"', $form);

        return redirect()->route('forms.edit', $form)->with('success', 'Formulário criado. Monte suas perguntas e publique.');
    }

    public function edit(Form $form)
    {
        $this->authorizeForm($form, 'forms.edit');
        $form->load('fields');

        return view('forms.builder', [
            'form' => $form,
            'fieldTypes' => FieldTypes::grouped(),
            'operators' => FormLogic::OPERATORS,
            'fieldsJson' => $form->fields->map->toBuilderArray()->values(),
        ]);
    }

    /** Salva o construtor (chamado via fetch, JSON). */
    public function saveBuilder(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'forms.edit');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'layout' => ['required', Rule::in(Form::LAYOUTS)],
            'theme' => ['array'],
            'theme.primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.background' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.text' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.font' => ['nullable', Rule::in(self::FONTS)],
            'theme.radius' => ['nullable', 'integer', 'min:0', 'max:28'],
            'theme.logo_url' => ['nullable', 'url', 'max:1000'],
            'theme.cover_url' => ['nullable', 'url', 'max:1000'],
            'fields' => ['array', 'max:200'],
            'fields.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'fields.*.type' => ['required', Rule::in(array_keys(FieldTypes::all()))],
            'fields.*.label' => ['required', 'string', 'max:500'],
            'fields.*.description' => ['nullable', 'string', 'max:2000'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:100'],
            'fields.*.options.*' => ['nullable', 'string', 'max:255'],
            'fields.*.settings' => ['nullable', 'array'],
        ], [
            'fields.*.label.required' => 'Toda pergunta precisa de um título.',
            'fields.*.key.distinct' => 'Há campos com a mesma chave.',
            'fields.*.key.regex' => 'A chave do campo deve ter apenas letras minúsculas, números e _.',
        ]);

        DB::transaction(function () use ($form, $data) {
            $form->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'layout' => $data['layout'],
                'theme' => array_merge(Form::DEFAULT_THEME, $form->theme ?? [], array_filter($data['theme'] ?? [], fn ($v) => $v !== null) + ['logo_url' => $data['theme']['logo_url'] ?? null, 'cover_url' => $data['theme']['cover_url'] ?? null]),
            ]);

            $keys = collect($data['fields'] ?? [])->pluck('key')->all();
            $form->fields()->whereNotIn('key', $keys ?: ['__none__'])->delete();

            foreach (array_values($data['fields'] ?? []) as $i => $f) {
                $options = FieldTypes::hasOptions($f['type'])
                    ? array_values(array_unique(array_filter(array_map('trim', $f['options'] ?? []), fn ($o) => $o !== '')))
                    : null;
                FormField::updateOrCreate(
                    ['form_id' => $form->id, 'key' => $f['key']],
                    [
                        'type' => $f['type'],
                        'label' => $f['label'],
                        'description' => $f['description'] ?? null,
                        'placeholder' => $f['placeholder'] ?? null,
                        'required' => FieldTypes::isInput($f['type']) && $f['type'] !== 'hidden' && ($f['required'] ?? false),
                        'options' => $options,
                        'settings' => $this->cleanSettings($f['settings'] ?? [], $keys),
                        'position' => $i,
                    ]
                );
            }
            $form->touch();
        });

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    private function cleanSettings(array $s, array $keys): ?array
    {
        $allowed = ['min', 'max', 'step', 'unit', 'max_length', 'rows', 'allow_other', 'max_choices', 'label_min', 'label_max', 'text', 'param', 'default', 'logic',
            'doc', 'accept', 'max_size', 'max_files', 'matrix_rows', 'number_optional'];
        $s = array_intersect_key($s, array_flip($allowed));
        if (isset($s['matrix_rows'])) {
            $s['matrix_rows'] = array_values(array_unique(array_filter(array_map(fn ($r) => mb_substr(trim((string) $r), 0, 255), (array) $s['matrix_rows']), fn ($r) => $r !== '')));
            $s['matrix_rows'] = array_slice($s['matrix_rows'], 0, 50) ?: null;
        }
        if (isset($s['doc']) && ! in_array($s['doc'], ['cpf', 'cnpj', 'both'], true)) {
            unset($s['doc']);
        }
        if (isset($s['accept']) && ! isset(FieldTypes::ACCEPT[$s['accept']])) {
            unset($s['accept']);
        }
        foreach (['max_size' => [1, (int) config('traceforms.upload_max_mb', 25)], 'max_files' => [1, 10]] as $k => [$lo, $hi]) {
            if (isset($s[$k]) && $s[$k] !== '') {
                $s[$k] = max($lo, min($hi, (int) $s[$k]));
            }
        }
        if (isset($s['logic'])) {
            $rules = array_values(array_filter((array) ($s['logic']['rules'] ?? []), fn ($r) => in_array($r['field'] ?? null, $keys, true) && isset(FormLogic::OPERATORS[$r['op'] ?? ''])));
            $s['logic'] = [
                'enabled' => (bool) ($s['logic']['enabled'] ?? false) && $rules,
                'match' => ($s['logic']['match'] ?? 'all') === 'any' ? 'any' : 'all',
                'rules' => array_map(fn ($r) => ['field' => $r['field'], 'op' => $r['op'], 'value' => mb_substr((string) ($r['value'] ?? ''), 0, 255)], $rules),
            ];
            if (! $s['logic']['enabled'] && ! $rules) {
                unset($s['logic']);
            }
        }
        $s = array_filter($s, fn ($v) => $v !== null && $v !== '');

        return $s ?: null;
    }

    public function settings(Form $form)
    {
        $this->authorizeForm($form, 'forms.edit');

        return view('forms.settings', ['form' => $form]);
    }

    public function updateSettings(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'forms.edit');
        $data = $request->validate([
            'submit_label' => ['required', 'string', 'max:60'],
            'success_title' => ['required', 'string', 'max:150'],
            'success_message' => ['nullable', 'string', 'max:2000'],
            'redirect_url' => ['nullable', 'url', 'max:1000'],
            'closed_message' => ['nullable', 'string', 'max:500'],
            'closes_at' => ['nullable', 'date'],
            'max_submissions' => ['nullable', 'integer', 'min:1'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
            'webhook_url' => ['nullable', 'url', 'starts_with:https://', 'max:1000'],
            'min_submit_seconds' => ['nullable', 'integer', 'min:0', 'max:120'],
            'ga4_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,15}$/'],
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]{6,20}$/'],
            'gtm_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
        ], [
            'ga4_id.regex' => 'Formato esperado: G-XXXXXXXXXX',
            'meta_pixel_id.regex' => 'O ID do Pixel tem apenas números.',
            'gtm_id.regex' => 'Formato esperado: GTM-XXXXXXX',
            'webhook_url.starts_with' => 'O webhook precisa usar https://',
            'url' => 'Informe uma URL válida.',
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['notify_emails'] ?? '')))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values()->all();

        $form->update(['settings' => array_merge($form->settings ?? [], $data, [
            'notify_emails' => $emails,
            'show_progress' => $request->boolean('show_progress'),
            'one_per_visitor' => $request->boolean('one_per_visitor'),
            'collect_ip' => $request->boolean('collect_ip'),
            'anonymize_ip' => $request->boolean('anonymize_ip'),
            'save_partials' => $request->boolean('save_partials'),
            'redirect_pass_params' => $request->boolean('redirect_pass_params'),
            'show_branding' => $request->boolean('show_branding'),
            'max_submissions' => $data['max_submissions'] ?? null,
            'min_submit_seconds' => (int) ($data['min_submit_seconds'] ?? 0),
        ])]);
        AuditLog::record('form.settings', 'Alterou as configurações de "'.$form->title.'"', $form);

        return back()->with('success', 'Configurações salvas.');
    }

    public function share(Form $form)
    {
        $this->authorizeForm($form, 'reports.view');
        $form->load(['trackingLinks' => fn ($q) => $q->withCount([
            'sessions as views_count',
            'sessions as submits_count' => fn ($q) => $q->where('status', 'submitted'),
        ])->latest()]);

        return view('forms.share', ['form' => $form]);
    }

    public function status(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'forms.publish');
        $status = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'closed'])]])['status'];

        if ($status === 'published' && $form->fields()->where('type', '!=', 'statement')->count() === 0) {
            return back()->with('error', 'Adicione pelo menos uma pergunta antes de publicar.');
        }
        $form->update(['status' => $status, 'published_at' => $status === 'published' ? ($form->published_at ?? now()) : $form->published_at]);
        AuditLog::record('form.'.$status, ['published' => 'Publicou', 'closed' => 'Encerrou', 'draft' => 'Despublicou'][$status].' "'.$form->title.'"', $form);

        return back()->with('success', match ($status) {
            'published' => 'Formulário publicado! Compartilhe o link para começar a coletar.',
            'closed' => 'Formulário encerrado. Ele não aceita mais respostas.',
            default => 'Formulário voltou para rascunho.',
        });
    }

    public function duplicate(Form $form)
    {
        $this->authorizeForm($form, 'forms.create');
        $copy = DB::transaction(function () use ($form) {
            $copy = $form->replicate(['public_id', 'published_at']);
            $copy->public_id = Form::generatePublicId();
            $copy->title = Str::limit($form->title, 180).' (cópia)';
            $copy->status = 'draft';
            $copy->created_by = auth()->id();
            $copy->save();
            foreach ($form->fields as $f) {
                $copy->fields()->create($f->only(['key', 'type', 'label', 'description', 'placeholder', 'required', 'options', 'settings', 'position']));
            }

            return $copy;
        });
        AuditLog::record('form.duplicated', 'Duplicou "'.$form->title.'"', $copy);

        return redirect()->route('forms.edit', $copy)->with('success', 'Cópia criada.');
    }

    public function destroy(Form $form)
    {
        $this->authorizeForm($form, 'forms.delete');
        $form->delete();
        AuditLog::record('form.deleted', 'Excluiu "'.$form->title.'"', $form);

        return redirect()->route('forms.index')->with('success', 'Formulário excluído.');
    }
}
