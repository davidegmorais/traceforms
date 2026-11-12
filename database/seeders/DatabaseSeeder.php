<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\Tracking\ChannelClassifier;
use App\Support\FormTemplates;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Conta de demonstração com equipe, formulários e ~90 dias de tráfego realista.
 * Login: demo@traceforms.test / traceforms123
 */
class DatabaseSeeder extends Seeder
{
    private array $firstNames = ['Ana', 'Bruno', 'Carla', 'Diego', 'Eduarda', 'Felipe', 'Gabriela', 'Henrique', 'Isabela', 'João', 'Larissa', 'Marcos', 'Natália', 'Otávio', 'Paula', 'Rafael', 'Sofia', 'Thiago', 'Vanessa', 'Lucas', 'Mariana', 'Pedro', 'Beatriz', 'Caio', 'Juliana'];

    private array $lastNames = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Lima', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Carvalho', 'Gomes', 'Ribeiro', 'Martins', 'Araújo'];

    public function run(): void
    {
        mt_srand(42);

        $owner = User::create(['name' => 'Demo TraceForms', 'email' => 'demo@traceforms.test']);
        $account = Account::createFor($owner, 'Acme Marketing');
        $account->update(['timezone' => 'America/Sao_Paulo']);

        $team = [
            ['Marina Admin', 'admin@traceforms.test', Role::Admin],
            ['Eduardo Editor', 'editor@traceforms.test', Role::Editor],
            ['Ana Analista', 'analista@traceforms.test', Role::Analyst],
            ['Leo Leitor', 'leitor@traceforms.test', Role::Viewer],
        ];
        foreach ($team as [$name, $email, $role]) {
            $u = User::create(['name' => $name, 'email' => $email, 'current_account_id' => $account->id]);
            $account->users()->attach($u->id, ['role' => $role->value]);
        }
        Account::createFor(User::where('email', 'admin@traceforms.test')->first(), 'Cliente Beta (agência)');
        User::where('email', 'admin@traceforms.test')->update(['current_account_id' => $account->id]);

        $forms = [
            $this->makeForm($account, $owner, 'Diagnóstico gratuito de marketing', 'lead', 'published', 'classic', '#5b4cf0', 'Responda em 2 minutos e receba um diagnóstico personalizado do seu marketing digital.'),
            $this->makeForm($account, $owner, 'Pesquisa NPS — clientes 2026', 'nps', 'published', 'conversational', '#22a06b', 'Sua opinião nos ajuda a melhorar. Leva menos de 1 minuto.'),
            $this->makeForm($account, $owner, 'Inscrição — Workshop Growth SP', 'event', 'closed', 'card', '#0090ff', 'Vagas limitadas. Garanta seu lugar no workshop presencial.'),
            $this->makeForm($account, $owner, 'Fale conosco (site)', 'contact', 'published', 'chat', '#2c4a43', 'Conte o que você precisa. Respondemos em até um dia útil.'),
            $this->makeForm($account, $owner, 'Trabalhe conosco — 2º semestre', 'job', 'published', 'classic', '#1d5fa8', 'Envie seu currículo. Respondemos todas as candidaturas em até 10 dias úteis.'),
            $this->makeForm($account, $owner, 'Pesquisa de clima — time interno', 'climate', 'published', 'conversational', '#6d3fc0', 'Anônima. Leva cerca de 3 minutos.'),
        ];

        // Restringe o leitor ao formulário de NPS para demonstrar o acesso por formulário.
        DB::table('account_user')->where('account_id', $account->id)->where('role', 'viewer')->update(['form_ids' => json_encode([$forms[1]->id])]);

        $links = [
            TrackingLink::create(['form_id' => $forms[0]->id, 'name' => 'Bio do Instagram', 'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'bio', 'clicks' => 0]),
            TrackingLink::create(['form_id' => $forms[0]->id, 'name' => 'Newsletter semanal', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'news_semanal', 'clicks' => 0]),
            TrackingLink::create(['form_id' => $forms[0]->id, 'name' => 'Grupo WhatsApp', 'utm_source' => 'whatsapp', 'utm_medium' => 'sms', 'utm_campaign' => 'grupo_vip', 'clicks' => 0]),
        ];

        $this->traffic($forms[0], 5200, 90, $links);
        $this->traffic($forms[1], 1900, 60, []);
        $this->traffic($forms[2], 1400, 75, [], 20);
        $this->traffic($forms[4], 700, 45, []);
        $this->traffic($forms[5], 420, 30, []);

        foreach ($links as $l) {
            $l->update(['clicks' => (int) ($l->sessions()->count() * 1.08)]);
        }

        app()->instance('account', $account);
        auth()->setUser($owner);
        AuditLog::record('form.published', 'Publicou "'.$forms[0]->title.'"', $forms[0]);
        AuditLog::record('invitation.sent', 'Convidou analista@traceforms.test como Analista');
        AuditLog::record('member.updated', 'Alterou Leo Leitor: restrito a 1 formulário(s)');
        AuditLog::record('export.csv', 'Exportou respostas de "'.$forms[1]->title.'"', $forms[1]);
    }

    private function makeForm(Account $account, User $user, string $title, string $tpl, string $status, string $layout, string $color, ?string $desc): Form
    {
        $form = $account->forms()->create([
            'title' => $title, 'description' => $desc, 'status' => $status, 'layout' => $layout, 'created_by' => $user->id,
            'theme' => ['primary' => $color, 'background' => '#eef3f1'],
            'published_at' => $status !== 'draft' ? now()->subDays(95) : null,
        ]);
        foreach (FormTemplates::fields($tpl) as $f) {
            $form->fields()->create($f);
        }
        if ($tpl === 'lead') {
            $form->fields()->create(['key' => 'vendedor', 'type' => 'hidden', 'label' => 'Vendedor (oculto)', 'settings' => ['param' => 'vendedor'], 'position' => 99]);
        }

        return $form->load('fields');
    }

    /** Gera sessões e respostas com distribuições plausíveis de canal, dispositivo e conversão. */
    private function traffic(Form $form, int $sessions, int $days, array $links, int $endedDaysAgo = 0): void
    {
        $sources = [
            // [peso, source, medium, campaign(s), referrer, click_id_type, conv mult]
            [24, 'google', 'cpc', ['brand_search', 'generic_marketing', 'concorrentes'], 'https://www.google.com/', 'gclid', 1.25],
            [20, 'facebook', 'paid_social', ['bf_2026_lookalike', 'retarget_30d', 'video_depoimentos'], 'https://l.facebook.com/', 'fbclid', 0.9],
            [10, 'instagram', 'paid_social', ['bf_2026_lookalike', 'stories_oferta'], 'https://l.instagram.com/', 'fbclid', 0.8],
            [12, null, null, null, 'https://www.google.com/', null, 1.1], // orgânico
            [11, null, null, null, null, null, 1.0], // direto
            [6, 'newsletter', 'email', ['news_semanal', 'reengajamento'], null, null, 1.6],
            [5, 'linkedin', 'social', ['post_ceo', 'carrossel_cases'], 'https://www.linkedin.com/', null, 1.15],
            [4, 'tiktok', 'paid_social', ['ugc_creators'], 'https://www.tiktok.com/', 'ttclid', 0.55],
            [3, 'whatsapp', 'sms', ['grupo_vip'], null, null, 1.9],
            [3, null, null, null, 'https://blog.parceiro.com.br/artigo-growth', null, 1.2],
            [2, 'bing', 'cpc', ['generic_marketing'], 'https://www.bing.com/', 'msclkid', 1.0],
        ];
        $devices = [
            [58, 'smartphone', ['Apple', 'Samsung', 'Motorola', 'Xiaomi'], ['iOS', 'Android'], [[390, 844], [393, 873], [412, 915], [360, 800], [430, 932]]],
            [36, 'desktop', [null], ['Windows', 'Mac', 'GNU/Linux'], [[1920, 1080], [1366, 768], [1536, 864], [1440, 900], [2560, 1440]]],
            [6, 'tablet', ['Apple', 'Samsung'], ['iPadOS', 'Android'], [[820, 1180], [810, 1080]]],
        ];
        $models = ['Apple' => ['iPhone 15', 'iPhone 14', 'iPhone 13', 'iPhone 16 Pro', 'iPad'], 'Samsung' => ['Galaxy S24', 'Galaxy A54', 'Galaxy A15', 'Galaxy Tab S9'], 'Motorola' => ['Moto G84', 'Moto G54', 'Edge 40'], 'Xiaomi' => ['Redmi Note 13', 'Poco X6']];
        $regions = [['SP', 'São Paulo', 30], ['RJ', 'Rio de Janeiro', 12], ['MG', 'Belo Horizonte', 10], ['CE', 'Fortaleza', 7], ['PR', 'Curitiba', 7], ['RS', 'Porto Alegre', 6], ['BA', 'Salvador', 6], ['PE', 'Recife', 5], ['DF', 'Brasília', 5], ['SC', 'Florianópolis', 4], ['GO', 'Goiânia', 3]];
        $classifier = new ChannelClassifier;
        $fields = $form->fields->values();
        $inputs = $fields->filter(fn ($f) => ! in_array($f->type, ['statement', 'hidden']))->values();
        $now = now()->subDays($endedDaysAgo);
        $visitors = [];
        $sessionRows = [];
        $pending = [];

        for ($i = 0; $i < $sessions; $i++) {
            // Mais tráfego recente e em dias úteis/horário comercial.
            $dayAgo = (int) floor(pow(mt_rand() / mt_getrandmax(), 1.35) * $days);
            $ts = Carbon::parse($now)->subDays($dayAgo)->setTime(0, 0)->addMinutes($this->hourMinute());
            if ($ts->isWeekend() && mt_rand(1, 100) <= 35) {
                $ts->subDays($ts->isSaturday() ? 1 : 2);
            }
            $src = $this->pick($sources);
            $dev = $this->pick($devices);
            $link = null;
            if ($links && mt_rand(1, 100) <= 14) {
                $link = $links[array_rand($links)];
                $src = [0, $link->utm_source, $link->utm_medium, [$link->utm_campaign], $link->utm_source === 'instagram' ? 'https://l.instagram.com/' : null, null, $link->utm_medium === 'email' ? 1.6 : 1.3];
            }
            [, $source, $medium, $camps, $ref, $clickType, $mult] = $src;
            $campaign = $camps ? $camps[array_rand($camps)] : null;
            $brand = $dev[2][array_rand($dev[2])];
            $os = $dev[1] === 'smartphone' ? ($brand === 'Apple' ? 'iOS' : 'Android') : ($dev[1] === 'tablet' ? ($brand === 'Apple' ? 'iPadOS' : 'Android') : $dev[3][$this->weighted([70, 25, 5])]);
            $browser = match ($os) {
                'iOS', 'iPadOS' => $this->weighted([85, 15]) ? 'Chrome Mobile iOS' : 'Mobile Safari',
                'Android' => ['Chrome Mobile', 'Samsung Browser', 'Chrome Mobile', 'Chrome Mobile', 'Facebook'][mt_rand(0, 4)],
                'Mac' => ['Chrome', 'Safari', 'Chrome'][mt_rand(0, 2)],
                default => ['Chrome', 'Chrome', 'Microsoft Edge', 'Firefox'][mt_rand(0, 3)],
            };
            if (in_array($source, ['facebook', 'instagram'], true) && $dev[1] === 'smartphone' && mt_rand(1, 100) <= 45) {
                $browser = $source === 'instagram' ? 'Instagram App' : 'Facebook';
            }
            $screen = $dev[4][array_rand($dev[4])];
            $reg = $this->pick(array_map(fn ($r) => [$r[2], $r[0], $r[1]], $regions));
            $country = mt_rand(1, 100) <= 95 ? 'BR' : ['PT', 'US', 'AR'][mt_rand(0, 2)];

            // Visitante recorrente em ~18% dos casos.
            $returning = $visitors && mt_rand(1, 100) <= 18;
            $vid = $returning ? $visitors[array_rand($visitors)] : (string) Str::uuid();
            $visitors[] = $vid;

            // Probabilidades: começar e concluir.
            $startP = 0.42 * ($dev[1] === 'desktop' ? 1.1 : 0.95) * min(1.4, $mult);
            $doneP = 0.62 * $mult * ($dev[1] === 'desktop' ? 1.12 : 0.92) * ($returning ? 1.2 : 1);
            $started = mt_rand() / mt_getrandmax() < $startP;
            $submitted = $started && mt_rand() / mt_getrandmax() < min(0.92, $doneP);

            $landing = $form->publicUrl(array_filter(['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign, $clickType => $clickType ? Str::random(24) : null, 'vendedor' => mt_rand(1, 3) === 1 ? ['carlos', 'bianca', 'rui'][mt_rand(0, 2)] : null]));
            $embedded = mt_rand(1, 100) <= 35;
            $stats = [];
            $partial = null;
            $reach = 0;
            $lastKey = null;
            $duration = null;
            if ($started) {
                $reach = $submitted ? $fields->count() : mt_rand(1, max(1, $fields->count() - 1));
                foreach ($fields->take($reach) as $f) {
                    if ($f->type === 'hidden') {
                        continue;
                    }
                    $base = ['long_text' => 22000, 'nps' => 4000, 'rating' => 3500, 'checkbox' => 7000, 'radio' => 4500, 'select' => 4000, 'phone' => 7500, 'email' => 6500, 'consent' => 2500, 'statement' => 1500][$f->type] ?? 5000;
                    $stats[$f->key] = ['ms' => (int) ($base * (0.5 + mt_rand() / mt_getrandmax() * 1.4) * ($dev[1] === 'desktop' ? 0.85 : 1.1)), 'focus' => mt_rand(1, 2), 'changes' => mt_rand(1, 6), 'errors' => in_array($f->type, ['email', 'phone'], true) && mt_rand(1, 100) <= 12 ? 1 : 0];
                    $lastKey = $f->key;
                }
                $duration = array_sum(array_column($stats, 'ms')) + mt_rand(3000, 15000);
                if (! $submitted) {
                    $partial = [];
                    foreach ($inputs as $f) {
                        if (isset($stats[$f->key]) && $f->key !== $lastKey) {
                            $partial[$f->key] = $this->answer($f);
                        }
                    }
                    $partial = $partial ?: null;
                }
            }

            $sessionRows[] = [
                'uuid' => (string) Str::uuid(), 'form_id' => $form->id, 'tracking_link_id' => $link?->id, 'visitor_id' => $vid,
                'is_returning' => $returning, 'visit_number' => $returning ? mt_rand(2, 4) : 1,
                'status' => $submitted ? 'submitted' : ($started ? 'started' : 'viewed'),
                'ip' => '177.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.0', 'country' => $country,
                'region' => $country === 'BR' ? $reg[1] : null, 'city' => $country === 'BR' ? $reg[2] : null,
                'user_agent' => 'Mozilla/5.0 (seed)', 'device_type' => $dev[1], 'device_brand' => $brand,
                'device_model' => $brand ? ($models[$brand][array_rand($models[$brand])] ?? null) : null,
                'os_name' => $os, 'os_version' => ['iOS' => '18.'.mt_rand(0, 6), 'iPadOS' => '18.'.mt_rand(0, 4), 'Android' => (string) mt_rand(12, 15), 'Windows' => '10', 'Mac' => '15.'.mt_rand(0, 3), 'GNU/Linux' => ''][$os] ?? null,
                'browser_name' => $browser, 'browser_version' => (string) mt_rand(120, 138).'.0', 'browser_engine' => str_contains($browser, 'Safari') || in_array($os, ['iOS', 'iPadOS']) ? 'WebKit' : 'Blink',
                'is_bot' => false, 'screen_width' => $screen[0], 'screen_height' => $screen[1],
                'viewport_width' => $screen[0], 'viewport_height' => $screen[1] - mt_rand(60, 160),
                'pixel_ratio' => $dev[1] === 'desktop' ? [1, 1.25, 1.5, 2][mt_rand(0, 3)] : [2, 3, 2.75][mt_rand(0, 2)],
                'touch' => $dev[1] !== 'desktop', 'language' => $country === 'BR' ? 'pt-BR' : ($country === 'PT' ? 'pt-PT' : ($country === 'AR' ? 'es-AR' : 'en-US')),
                'languages' => 'pt-BR,pt,en', 'timezone' => $country === 'BR' ? ($reg[1] === 'CE' ? 'America/Fortaleza' : ($reg[1] === 'PE' ? 'America/Recife' : ($reg[1] === 'BA' ? 'America/Bahia' : 'America/Sao_Paulo'))) : 'Europe/Lisbon',
                'timezone_offset' => -180, 'connection_type' => $dev[1] === 'desktop' ? '4g' : ['4g', '4g', '4g', '3g'][mt_rand(0, 3)], 'connection_downlink' => mt_rand(15, 100) / 10,
                'dark_mode' => mt_rand(1, 100) <= 38, 'cookies_enabled' => true, 'do_not_track' => mt_rand(1, 100) <= 4,
                'hardware_concurrency' => [4, 6, 8, 8, 12][mt_rand(0, 4)], 'device_memory' => [4, 8, 8][mt_rand(0, 2)],
                'referrer' => $ref, 'referrer_domain' => $ref ? preg_replace('/^www\./', '', parse_url($ref, PHP_URL_HOST)) : null,
                'channel' => $classifier->classify($ref ? parse_url($ref, PHP_URL_HOST) : null, $source, $medium, $clickType),
                'landing_url' => $landing, 'parent_url' => $embedded ? 'https://www.acmemarketing.com.br/'.['diagnostico', 'contato', 'blog/guia-trafego-pago', ''][mt_rand(0, 3)] : null,
                'embed_mode' => $link ? 'link' : ($embedded ? 'iframe' : 'direct'),
                'utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign,
                'utm_term' => $medium === 'cpc' ? ['agencia de marketing', 'diagnostico marketing digital', 'consultoria growth', 'trafego pago'][mt_rand(0, 3)] : null,
                'utm_content' => in_array($medium, ['paid_social'], true) ? ['video_15s', 'carrossel', 'estatico_azul', 'depoimento_cliente'][mt_rand(0, 3)] : null,
                'utm_id' => null, 'click_id_type' => $clickType, 'click_id' => $clickType ? Str::random(32) : null,
                'first_touch' => json_encode($returning && mt_rand(1, 2) === 1
                    ? ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'generic_marketing', 'ts' => $ts->copy()->subDays(mt_rand(3, 30))->toIso8601String()]
                    : array_filter(['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign, 'referrer' => $ref, 'ts' => $ts->toIso8601String()])),
                'query_params' => null, 'hidden_fields' => null,
                'time_to_start_ms' => $started ? mt_rand(1500, 25000) : null, 'duration_ms' => $duration,
                'active_ms' => $duration ? (int) ($duration * (0.7 + mt_rand(0, 25) / 100)) : null,
                'max_step' => $reach, 'last_field_key' => $lastKey, 'field_stats' => $stats ? json_encode($stats) : null,
                'partial_data' => $partial ? json_encode($partial) : null, 'tab_switches' => $started ? $this->weighted([60, 25, 10, 5]) : 0,
                'paste_count' => $started && mt_rand(1, 100) <= 9 ? 1 : 0,
                'started_at' => $started ? $ts->copy()->addSeconds(mt_rand(2, 25)) : null,
                'submitted_at' => $submitted ? $ts->copy()->addMilliseconds($duration) : null,
                'last_activity_at' => $ts->copy()->addMilliseconds($duration ?? 4000),
                'created_at' => $ts, 'updated_at' => $ts,
            ];
            if ($submitted) {
                $pending[count($sessionRows) - 1] = true;
            }

            if (count($sessionRows) >= 400 || $i === $sessions - 1) {
                $this->flush($form, $sessionRows, $pending, $inputs);
                $sessionRows = [];
                $pending = [];
            }
        }
    }

    private function flush(Form $form, array $rows, array $pending, $inputs): void
    {
        DB::table('form_sessions')->insert($rows);
        $ids = DB::table('form_sessions')->whereIn('uuid', array_column($rows, 'uuid'))->pluck('id', 'uuid');
        $subs = [];
        foreach ($pending as $idx => $_) {
            $r = $rows[$idx];
            $data = [];
            $name = $this->firstNames[array_rand($this->firstNames)].' '.$this->lastNames[array_rand($this->lastNames)];
            foreach ($form->fields as $f) {
                if ($f->type === 'statement') {
                    continue;
                }
                if ($f->type === 'hidden') {
                    parse_str((string) parse_url($r['landing_url'], PHP_URL_QUERY), $q);
                    $data[$f->key] = $q[$f->setting('param') ?: $f->key] ?? null;

                    continue;
                }
                $data[$f->key] = $this->answer($f, $name, $data);
            }
            $isSpam = mt_rand(1, 100) <= 2;
            $subs[] = [
                'uuid' => (string) Str::uuid(), 'form_id' => $form->id, 'form_session_id' => $ids[$r['uuid']],
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'is_spam' => $isSpam, 'spam_reason' => $isSpam ? 'rápido demais (1.2s)' : null,
                'starred' => mt_rand(1, 100) <= 6, 'notes' => null,
                'submitted_at' => $r['submitted_at'], 'created_at' => $r['submitted_at'], 'updated_at' => $r['submitted_at'],
            ];
            if ($isSpam) {
                DB::table('form_sessions')->where('id', $ids[$r['uuid']])->update(['status' => 'spam']);
            }
        }
        if ($subs) {
            DB::table('submissions')->insert($subs);
            $this->seedFiles($form, array_column($subs, 'uuid'));
        }
    }

    /** Cria um PDF simples de currículo para cada resposta com campo de arquivo. */
    private function seedFiles(Form $form, array $uuids): void
    {
        $fileFields = $form->fields->where('type', 'file');
        if ($fileFields->isEmpty()) {
            return;
        }
        foreach (\App\Models\Submission::whereIn('uuid', $uuids)->get() as $sub) {
            $data = $sub->data;
            foreach ($fileFields as $f) {
                $name = Str::slug(Str::before((string) collect($data)->first(fn ($v) => is_string($v) && str_contains($v, ' ')), ' ') ?: 'candidato');
                $uuid = (string) Str::uuid();
                $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
                $path = "uploads/{$form->id}/{$uuid}.pdf";
                \Illuminate\Support\Facades\Storage::disk('local')->put($path, $pdf);
                $up = \App\Models\Upload::create(['uuid' => $uuid, 'form_id' => $form->id, 'form_session_id' => $sub->form_session_id, 'submission_id' => $sub->id,
                    'field_key' => $f->key, 'original_name' => 'curriculo-'.$name.'.pdf', 'mime' => 'application/pdf', 'size' => mt_rand(80, 900) * 1024, 'disk' => 'local', 'path' => $path]);
                $data[$f->key] = [$up->toAnswer()];
            }
            $sub->update(['data' => $data]);
        }
    }

    private function cpf(): string
    {
        $n = array_map(fn () => mt_rand(0, 9), range(1, 9));
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += $n[$i] * (($t + 1) - $i);
            }
            $n[$t] = ((10 * $s) % 11) % 10;
        }

        return \App\Support\BrDocs::format(implode('', $n));
    }

    private function answer($f, ?string $name = null, array $data = []): mixed
    {
        $name ??= $this->firstNames[array_rand($this->firstNames)].' '.$this->lastNames[array_rand($this->lastNames)];
        $logic = $f->setting('logic');
        if ($logic && ! empty($logic['enabled']) && ! \App\Services\FormLogic::isVisible($f, $data)) {
            return null;
        }

        return match ($f->type) {
            'short_text' => str_contains(mb_strtolower($f->label), 'empresa') ? ['Padaria Pão Quente', 'TechNova', 'Studio Lume', 'Clínica Vida', 'Loja Bella', 'Construtora Alfa', 'Agência Pixel'][mt_rand(0, 6)]
                : (str_contains(mb_strtolower($f->label), 'cargo') ? ['CEO', 'Gerente de Marketing', 'Analista', 'Sócio', 'Coordenador'][mt_rand(0, 4)]
                : (str_contains(mb_strtolower($f->label), 'restrição') ? ['Vegetariano', 'Sem lactose', 'Sem glúten'][mt_rand(0, 2)] : $name)),
            'email' => Str::slug(Str::before($name, ' ')).'.'.Str::slug(Str::after($name, ' ')).mt_rand(1, 99).'@'.['gmail.com', 'hotmail.com', 'outlook.com', 'empresa.com.br'][mt_rand(0, 3)],
            'phone' => '('.['11', '21', '31', '85', '41', '51'][mt_rand(0, 5)].') 9'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999),
            'radio', 'select' => $f->options[$this->weighted(array_map(fn ($i) => max(1, 10 - $i * 2), array_keys($f->options)))],
            'checkbox' => array_values(array_intersect_key($f->options, array_flip(array_rand($f->options, mt_rand(1, min(3, count($f->options))))))),
            'yes_no' => mt_rand(1, 100) <= 62 ? 'Sim' : 'Não',
            'nps' => [10, 10, 9, 9, 9, 8, 8, 7, 10, 6, 5, 9, 3, 10, 8, 9][mt_rand(0, 15)],
            'rating' => [5, 5, 4, 4, 4, 3, 5, 2][mt_rand(0, 7)],
            'scale' => mt_rand(3, (int) $f->setting('max', 5)),
            'consent' => 'Aceito',
            'cpf_cnpj' => $this->cpf(),
            'address' => [
                ['cep' => '60165-121', 'street' => 'Avenida Beira Mar', 'district' => 'Meireles', 'city' => 'Fortaleza', 'state' => 'CE'],
                ['cep' => '01310-100', 'street' => 'Avenida Paulista', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'],
                ['cep' => '22071-900', 'street' => 'Avenida Atlântica', 'district' => 'Copacabana', 'city' => 'Rio de Janeiro', 'state' => 'RJ'],
                ['cep' => '30130-110', 'street' => 'Avenida Afonso Pena', 'district' => 'Centro', 'city' => 'Belo Horizonte', 'state' => 'MG'],
                ['cep' => '80020-310', 'street' => 'Rua XV de Novembro', 'district' => 'Centro', 'city' => 'Curitiba', 'state' => 'PR'],
                ['cep' => '51020-000', 'street' => 'Avenida Boa Viagem', 'district' => 'Boa Viagem', 'city' => 'Recife', 'state' => 'PE'],
            ][mt_rand(0, 5)] + ['number' => (string) mt_rand(10, 2500)],
            'currency' => (float) (mt_rand(5, 24) * 500),
            'slider' => mt_rand(6, 19) * 5,
            'date' => now()->addDays(mt_rand(7, 60))->format('Y-m-d'),
            'time' => sprintf('%02d:%02d', mt_rand(8, 18), [0, 15, 30, 45][mt_rand(0, 3)]),
            'url' => 'https://www.linkedin.com/in/'.Str::slug($name),
            'ranking' => (function () use ($f) { $o = $f->options; usort($o, fn ($a, $b) => (array_search($a, $f->options) + mt_rand(-2, 2)) <=> (array_search($b, $f->options) + mt_rand(-2, 2))); return $o; })(),
            'matrix' => collect($f->matrixRows())->mapWithKeys(fn ($r, $i) => [$r => $f->options[$this->weighted($i === 1 ? [4, 3, 3] : [1, 3, 6])] ?? $f->options[0]])->all(),
            'file', 'signature' => null,
            'long_text' => mt_rand(1, 100) <= 70 ? ['Atendimento excelente, muito rápido.', 'Gostaria de mais relatórios personalizados.', 'O preço poderia ser melhor para pequenas empresas.', 'A plataforma é muito intuitiva!', 'Preciso de ajuda com tráfego pago e funil de vendas.', 'Quero aumentar meus leads no Instagram.', 'Faltou integração com meu CRM.'][mt_rand(0, 6)] : null,
            default => null,
        };
    }

    private function pick(array $items): array
    {
        return $items[$this->weighted(array_column($items, 0))];
    }

    private function weighted(array $weights): int
    {
        $r = mt_rand(1, (int) array_sum($weights));
        foreach ($weights as $i => $w) {
            if (($r -= $w) <= 0) {
                return $i;
            }
        }

        return 0;
    }

    private function hourMinute(): int
    {
        $hours = [0 => 1, 1 => 1, 6 => 2, 7 => 4, 8 => 7, 9 => 10, 10 => 11, 11 => 10, 12 => 7, 13 => 8, 14 => 10, 15 => 10, 16 => 9, 17 => 8, 18 => 7, 19 => 7, 20 => 8, 21 => 7, 22 => 5, 23 => 3];
        $h = array_keys($hours)[$this->weighted(array_values($hours))];

        return $h * 60 + mt_rand(0, 59) + 180; // horário local → UTC (−3h)
    }
}
