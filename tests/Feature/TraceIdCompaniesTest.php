<?php

namespace Tests\Feature;

use App\Console\Commands\TraceSync;
use App\Models\Account;
use App\Models\User;
use App\Services\TraceId\AccountChoices;
use App\Services\TraceId\TraceIdSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Várias empresas pelo Trace ID e a suspensão por licença que ficou para trás. */
class TraceIdCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://id.teste.local';

    protected function setUp(): void
    {
        parent::setUp();
        config(['traceid.issuer' => self::URL, 'traceid.client_id' => 'forms', 'traceid.client_secret' => 'segredo']);
        Http::fake([
            self::URL.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::URL, 'authorization_endpoint' => self::URL.'/oauth/authorize', 'token_endpoint' => self::URL.'/oauth/token',
                'userinfo_endpoint' => self::URL.'/oauth/userinfo', 'revocation_endpoint' => self::URL.'/oauth/revoke',
                'end_session_endpoint' => self::URL.'/oauth/logout', 'jwks_uri' => self::URL.'/oauth/jwks',
            ]),
            self::URL.'/oauth/token' => Http::response(['access_token' => 'plataforma', 'expires_in' => 3600]),
        ]);
    }

    private function claims(string $sub, string $email, string $account, string $name = 'Ara Growth'): array
    {
        return ['sub' => $sub, 'email' => $email, 'name' => 'Pessoa', 'trace' => ['account' => ['id' => $account, 'name' => $name], 'level' => 'admin', 'features' => []]];
    }

    public function test_login_desfaz_suspensao_por_licenca_antiga(): void
    {
        $uuid = (string) Str::uuid();
        $sub = (string) Str::uuid();
        [, $account] = app(TraceIdSync::class)->fromClaims($this->claims($sub, 'david@teste.local', $uuid));
        $account->forceFill(['suspended_at' => now(), 'suspension_reason' => TraceSync::REASON])->save();

        // O Trace ID entregou o token: a licença está válida. A suspensão antiga sai.
        app(TraceIdSync::class)->fromClaims($this->claims($sub, 'david@teste.local', $uuid));
        $this->assertFalse($account->fresh()->isSuspended());

        // Suspensão feita à mão (outro motivo) continua.
        $account->refresh()->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Bloqueio manual'])->save();
        app(TraceIdSync::class)->fromClaims($this->claims($sub, 'david@teste.local', $uuid));
        $this->assertTrue($account->fresh()->isSuspended());
    }

    public function test_seletor_mostra_todas_as_empresas_do_trace_id(): void
    {
        $sub = (string) Str::uuid();
        $ara = (string) Str::uuid();
        $outra = (string) Str::uuid();
        [$user] = app(TraceIdSync::class)->fromClaims($this->claims($sub, 'david@teste.local', $ara));

        Http::fake([self::URL."/api/v1/users/{$sub}" => Http::response(['data' => ['memberships' => [
            ['account' => ['id' => $ara, 'name' => 'Ara Growth'], 'level' => 'admin', 'status' => 'active'],
            ['account' => ['id' => $outra, 'name' => 'Outra Empresa'], 'level' => 'gestor', 'status' => 'active'],
        ]]])]);

        $choices = app(AccountChoices::class)->for($user);
        $this->assertSame(['Ara Growth', 'Outra Empresa'], $choices->pluck('name')->all());
        $this->assertNull($choices->firstWhere('uuid', $outra)->local, 'ainda não entrou nela, mas aparece');
    }

    public function test_abrir_pelo_trace_id_leva_a_empresa(): void
    {
        $target = (string) Str::uuid();
        $this->get('/?account='.$target)->assertRedirect(route('auth.trace.redirect', ['account' => $target]));
        $this->get('/login?account='.$target)->assertRedirect(route('auth.trace.redirect', ['account' => $target]));
    }
}
