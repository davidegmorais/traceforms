<?php

namespace App\Enums;

/**
 * Níveis de acesso por conta (workspace). Vêm do nível da pessoa no Trace ID
 * (veja config/traceid.php). "Proprietário" só existe em dados anteriores ao Trace ID.
 *
 * Hierarquia: owner > admin > editor > analyst > viewer.
 * Cada papel herda tudo do papel abaixo e acrescenta permissões.
 * Papéis abaixo de admin podem, opcionalmente, ser restritos a formulários específicos.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Analyst = 'analyst';
    case Viewer = 'viewer';

    /** Matriz de permissões: habilidade => papel mínimo. */
    public const ABILITIES = [
        'reports.view'      => self::Viewer,
        'responses.view'    => self::Analyst,  // dados brutos / PII
        'responses.export'  => self::Analyst,
        'responses.manage'  => self::Editor,   // marcar, anotar, excluir, spam
        'forms.create'      => self::Editor,
        'forms.edit'        => self::Editor,
        'forms.publish'     => self::Editor,
        'links.manage'      => self::Editor,
        'forms.delete'      => self::Admin,
        'members.manage'    => self::Admin,  // restringir membros a formulários
        'account.settings'  => self::Admin,
        'audit.view'        => self::Admin,
    ];

    public const ABILITY_LABELS = [
        'reports.view'     => 'Ver painéis e relatórios agregados',
        'responses.view'   => 'Ver respostas individuais (dados pessoais)',
        'responses.export' => 'Exportar respostas em CSV',
        'responses.manage' => 'Marcar, anotar e excluir respostas',
        'forms.create'     => 'Criar formulários',
        'forms.edit'       => 'Editar formulários e configurações',
        'forms.publish'    => 'Publicar / encerrar formulários',
        'links.manage'     => 'Criar links rastreados (UTM)',
        'forms.delete'     => 'Excluir formulários',
        'members.manage'   => 'Restringir membros a formulários',
        'account.settings' => 'Alterar configurações da empresa',
        'audit.view'       => 'Ver log de auditoria',
    ];

    public function level(): int
    {
        return match ($this) {
            self::Owner => 50,
            self::Admin => 40,
            self::Editor => 30,
            self::Analyst => 20,
            self::Viewer => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Admin => 'Administrador',
            self::Editor => 'Editor',
            self::Analyst => 'Analista',
            self::Viewer => 'Leitor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Controle total (papel anterior ao Trace ID).',
            self::Admin => 'Configurações da empresa, todos os formulários e restrições da equipe.',
            self::Editor => 'Cria, edita e publica formulários; gerencia respostas.',
            self::Analyst => 'Acessa relatórios e respostas completas, e exporta dados. Não edita.',
            self::Viewer => 'Vê apenas painéis agregados — sem acesso a dados pessoais.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'is-dark',
            self::Admin => 'is-danger is-light',
            self::Editor => 'is-link is-light',
            self::Analyst => 'is-info is-light',
            self::Viewer => 'is-light',
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->level() >= $other->level();
    }

    public function can(string $ability): bool
    {
        $min = self::ABILITIES[$ability] ?? null;

        return $min !== null && $this->atLeast($min);
    }

    /** Papéis que podem ser restritos a formulários específicos. */
    public function scopable(): bool
    {
        return $this->level() < self::Admin->level();
    }

    /** Papéis que este papel pode atribuir a outros. */
    public function assignable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $r) => $r !== self::Owner && ($this === self::Owner || $r->level() < $this->level())
        ));
    }
}
