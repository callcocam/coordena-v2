<?php

namespace App\Enums;

/**
 * The global (catalog) cargos seeded for every congregation, keyed by their
 * stable slug. Each case carries its own seed definition so the seeder,
 * team creation, and invitation acceptance share one source of truth.
 */
enum DefaultCargo: string
{
    case Coordenador = 'coordenador';
    case Secretario = 'secretario';
    case SuperintendenteServico = 'superintendente-servico';
    case Publicador = 'publicador';
    case ServoMinisterial = 'servo-ministerial';
    case CoordenadorDiscursos = 'coordenador-discursos';
    case Ajudante = 'ajudante';

    /**
     * The human-readable pt-BR name of the cargo.
     */
    public function label(): string
    {
        return match ($this) {
            self::Coordenador => 'Coordenador do corpo de anciãos',
            self::Secretario => 'Secretário',
            self::SuperintendenteServico => 'Superintendente de serviço',
            self::Publicador => 'Publicador',
            self::ServoMinisterial => 'Servo ministerial',
            self::CoordenadorDiscursos => 'Coordenador de discursos',
            self::Ajudante => 'Ajudante',
        };
    }

    /**
     * Whether this cargo grants every permission (acts as team super admin).
     */
    public function isSuper(): bool
    {
        return $this === self::Coordenador;
    }

    /**
     * Whether this cargo is assigned to every member on joining a team.
     */
    public function isDefault(): bool
    {
        return $this === self::Publicador;
    }

    /**
     * The permission names granted by this cargo.
     *
     * Super cargos short-circuit to the whole catalog at runtime, so they
     * carry no explicit permissions here.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Coordenador => [],
            self::Secretario => [
                'team:update',
                'member:add',
                'member:update',
                'member:remove',
                'invitation:create',
                'invitation:cancel',
                'congregation:view',
                'congregation:update',
                'report:view',
                'report:submit',
            ],
            self::SuperintendenteServico => [
                'service:view',
                'service:manage',
                'congregation:view',
            ],
            self::CoordenadorDiscursos => [
                'congregation:view',
                'congregations:view',
                'congregations:manage',
                'public-talks:view',
                'public-talks:manage',
                'public-talks:notify',
            ],
            self::Ajudante => [
                'congregation:view',
                'congregations:view',
                'public-talks:view',
                'public-talks:manage',
            ],
            self::Publicador,
            self::ServoMinisterial => [
                'congregation:view',
            ],
        };
    }
}
