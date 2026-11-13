<?php

namespace App\Services\TraceId;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente do Trace ID (OpenID Connect + API de acessos).
 *
 * Login: authorizeUrl() -> exchangeCode() -> verifyIdToken().
 * Sessão: refresh() quando o access_token vence; userinfo() para conferir o acesso.
 * API: api() com token client_credentials (members:read / members:write).
 */
class TraceIdClient
{
    public function configured(): bool
    {
        return filled(config('traceid.client_id')) && filled(config('traceid.client_secret'));
    }

    public function issuer(): string
    {
        return config('traceid.issuer');
    }

    public function redirectUri(): string
    {
        return config('traceid.redirect_uri') ?: rtrim(config('app.url'), '/').'/auth/callback';
    }

    /** Metadados OIDC, com endereços padrão caso a descoberta falhe. */
    public function discovery(): array
    {
        $defaults = [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->issuer().'/oauth/authorize',
            'token_endpoint' => $this->issuer().'/oauth/token',
            'userinfo_endpoint' => $this->issuer().'/oauth/userinfo',
            'jwks_uri' => $this->issuer().'/oauth/jwks',
            'revocation_endpoint' => $this->issuer().'/oauth/revoke',
            'end_session_endpoint' => $this->issuer().'/oauth/logout',
        ];

        $meta = Cache::remember('traceid.discovery', 3600, function () {
            try {
                $r = $this->http()->get($this->issuer().'/.well-known/openid-configuration');

                return $r->successful() ? (array) $r->json() : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return array_merge($defaults, array_filter($meta, fn ($v) => is_string($v) && $v !== ''), array_filter($meta, 'is_array'));
    }

    public function authorizeUrl(string $state, string $nonce, string $codeChallenge, array $extra = []): string
    {
        $params = array_filter([
            'client_id' => config('traceid.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => config('traceid.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ] + $extra, fn ($v) => $v !== null && $v !== '');

        return $this->discovery()['authorization_endpoint'].'?'.http_build_query($params);
    }

    public function exchangeCode(string $code, string $verifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
        ]);
    }

    /** Renova os tokens. Lança TraceIdException se o refresh_token não vale mais. */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /** Dados atuais da pessoa (conta, nível, recursos). Null quando o acesso foi suspenso/removido. */
    public function userinfo(string $accessToken): ?array
    {
        $r = $this->http()->withToken($accessToken)->get($this->discovery()['userinfo_endpoint']);
        if ($r->status() === 401) {
            return null;
        }
        $this->failUnless($r, 'Não foi possível conferir seu acesso no Trace ID.');

        return (array) $r->json();
    }

    public function revoke(string $token, string $hint = 'refresh_token'): void
    {
        try {
            $this->http()->asForm()
                ->withBasicAuth(config('traceid.client_id'), config('traceid.client_secret'))
                ->post($this->discovery()['revocation_endpoint'], ['token' => $token, 'token_type_hint' => $hint]);
        } catch (\Throwable) {
            // Revogar é cortesia: a sessão local já foi encerrada.
        }
    }

    public function logoutUrl(?string $idToken, string $afterLogout): string
    {
        return $this->discovery()['end_session_endpoint'].'?'.http_build_query(array_filter([
            'id_token_hint' => $idToken,
            'client_id' => config('traceid.client_id'),
            'post_logout_redirect_uri' => $afterLogout,
        ]));
    }

    /**
     * Confere assinatura (RS256, chaves do JWKS), emissor, público, validade e nonce do id_token.
     * Devolve as claims.
     */
    public function verifyIdToken(string $jwt, ?string $nonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new TraceIdException('Resposta inválida do Trace ID (token mal formado).');
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::b64url($h64), true);
        $claims = json_decode(self::b64url($p64), true);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256') {
            throw new TraceIdException('Resposta inválida do Trace ID (token não assinado com RS256).');
        }

        $pem = $this->publicKey($header['kid'] ?? null);
        $ok = openssl_verify("$h64.$p64", self::b64url($s64), $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new TraceIdException('A assinatura do token do Trace ID não confere.');
        }

        $now = time();
        $aud = (array) ($claims['aud'] ?? []);
        $leeway = 60;
        $checks = [
            'emissor' => rtrim($claims['iss'] ?? '', '/') === $this->issuer(),
            'público' => in_array(config('traceid.client_id'), $aud, true),
            'validade' => isset($claims['exp']) && $claims['exp'] + $leeway >= $now,
            'emissão' => ! isset($claims['iat']) || $claims['iat'] - $leeway <= $now,
            'nonce' => $nonce === null || hash_equals($nonce, (string) ($claims['nonce'] ?? '')),
            'pessoa' => ! empty($claims['sub']),
        ];
        foreach ($checks as $what => $passed) {
            if (! $passed) {
                throw new TraceIdException("Token do Trace ID recusado ({$what} não confere).");
            }
        }

        return $claims;
    }

    // ------------------------------------------------------------------
    // API de acessos (client_credentials)
    // ------------------------------------------------------------------

    public function api(string $method, string $path, array $data = [], bool $retry = true): Response
    {
        $token = Cache::remember('traceid.client_token', 3000, function () {
            $t = $this->tokenRequest(['grant_type' => 'client_credentials', 'scope' => 'members:read members:write']);

            return $t['access_token'];
        });

        $url = $this->issuer().'/api/v1/'.ltrim($path, '/');
        $req = $this->http()->withToken($token);
        $r = strtolower($method) === 'get' ? $req->get($url, $data) : $req->send(strtoupper($method), $url, ['json' => $data]);

        // Token da plataforma revogado ou vencido antes da hora: pega outro e tenta uma vez.
        if ($r->status() === 401 && $retry) {
            Cache::forget('traceid.client_token');

            return $this->api($method, $path, $data, false);
        }

        return $r;
    }

    /**
     * Empresas em que a pessoa tem acesso ao TraceForms no Trace ID, mesmo as em que ela ainda não entrou.
     *
     * @return list<array{id: string, name: string, level: string, status: string}>
     */
    public function userAccounts(string $traceUserId): array
    {
        $r = $this->api('get', "users/{$traceUserId}");
        if ($r->status() === 404) {
            return [];
        }
        $this->failUnless($r, 'Não foi possível listar as empresas da pessoa no Trace ID.');

        return collect($r->json('data.memberships') ?? [])->map(fn ($m) => [
            'id' => (string) ($m['account']['id'] ?? ''),
            'name' => (string) ($m['account']['name'] ?? ''),
            'level' => (string) ($m['level'] ?? ''),
            'status' => (string) ($m['status'] ?? 'active'),
        ])->filter(fn ($a) => $a['id'] !== '')->values()->all();
    }

    /** Todas as páginas de membros de uma conta. */
    public function members(string $accountUuid): array
    {
        $all = [];
        $page = 1;
        do {
            $r = $this->api('get', "accounts/{$accountUuid}/members", ['per_page' => 200, 'page' => $page]);
            $this->failUnless($r, 'Não foi possível listar os acessos da conta no Trace ID.');
            $batch = $r->json('data') ?? [];
            $all = array_merge($all, $batch);
            $last = $r->json('meta.last_page');
            $page++;
        } while (count($batch) === 200 && ($last === null || $page <= (int) $last));

        return $all;
    }

    // ------------------------------------------------------------------

    private function tokenRequest(array $form): array
    {
        $r = $this->http()->asForm()
            ->withBasicAuth(config('traceid.client_id'), config('traceid.client_secret'))
            ->post($this->discovery()['token_endpoint'], $form);

        if ($r->status() === 400 && $r->json('error') === 'invalid_grant') {
            throw new TraceIdException('Sua sessão no Trace ID terminou. Entre de novo.', 'invalid_grant');
        }
        if ($r->status() === 401) {
            throw new TraceIdException('O TraceForms não foi reconhecido pelo Trace ID. Confira TRACE_ID_CLIENT_ID e TRACE_ID_CLIENT_SECRET no .env.', 'invalid_client');
        }
        $this->failUnless($r, 'O Trace ID não respondeu como esperado. Tente de novo em instantes.');

        return (array) $r->json();
    }

    private function publicKey(?string $kid): string
    {
        $find = function (array $jwks) use ($kid) {
            foreach ($jwks['keys'] ?? [] as $k) {
                if (($k['kty'] ?? '') === 'RSA' && ($kid === null || ($k['kid'] ?? null) === $kid)) {
                    return $k;
                }
            }

            return null;
        };

        $jwks = Cache::remember('traceid.jwks', 900, fn () => (array) $this->http()->get($this->discovery()['jwks_uri'])->json());
        $key = $find($jwks);
        if (! $key) {
            // Chave nova após rotação: busca de novo uma vez.
            Cache::forget('traceid.jwks');
            $jwks = Cache::remember('traceid.jwks', 900, fn () => (array) $this->http()->get($this->discovery()['jwks_uri'])->json());
            $key = $find($jwks);
        }
        if (! $key) {
            throw new TraceIdException('Chave de assinatura do Trace ID não encontrada.');
        }

        return self::rsaPem(self::b64url($key['n']), self::b64url($key['e']));
    }

    /** Monta a chave pública PEM (SubjectPublicKeyInfo) a partir do módulo e do expoente. */
    private static function rsaPem(string $n, string $e): string
    {
        $int = function (string $x) {
            if (ord($x[0]) > 0x7F) {
                $x = "\x00".$x;
            }

            return "\x02".self::derLen(strlen($x)).$x;
        };
        $rsaKey = "\x30".self::derLen(strlen($int($n).$int($e))).$int($n).$int($e);
        $bitString = "\x03".self::derLen(strlen($rsaKey) + 1)."\x00".$rsaKey;
        $algo = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";
        $spki = "\x30".self::derLen(strlen($algo.$bitString)).$algo.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    public static function b64url(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    private function http(): PendingRequest
    {
        return Http::timeout(config('traceid.http_timeout', 10))->acceptJson()->withHeaders(['User-Agent' => 'TraceForms/1.0']);
    }

    private function failUnless(Response $r, string $message): void
    {
        if (! $r->successful()) {
            throw new TraceIdException($message, 'http_'.$r->status());
        }
    }
}
