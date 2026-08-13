<?php

namespace App\Support;

/**
 * Single source of truth for the fine-grained `resource:action` permissions.
 *
 * The catalog is defined in code and seeded into the `permissions` table.
 * It starts lean (only what already exists plus clearly-labelled placeholders
 * for congregation/field-service features) and grows as features arrive.
 */
class PermissionCatalog
{
    /**
     * Permissions grouped by resource: group => [name => label].
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'team' => [
                'team:update' => 'Editar congregação',
                'team:delete' => 'Excluir congregação',
            ],
            'member' => [
                'member:add' => 'Adicionar membro',
                'member:update' => 'Atualizar cargos de membro',
                'member:remove' => 'Remover membro',
            ],
            'invitation' => [
                'invitation:create' => 'Criar convite',
                'invitation:cancel' => 'Cancelar convite',
            ],
            'role' => [
                'role:manage' => 'Gerenciar cargos',
            ],
            'congregation' => [
                'congregation:view' => 'Ver dados da congregação',
                'congregation:update' => 'Atualizar dados da congregação',
                'report:view' => 'Ver relatórios de serviço',
                'report:submit' => 'Enviar relatórios de serviço',
            ],
            'service' => [
                'service:view' => 'Ver serviço de campo',
                'service:manage' => 'Gerenciar serviço de campo',
            ],
        ];
    }

    /**
     * Flat map of permission name => label.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(static::groups()));
    }

    /**
     * Every permission name in the catalog.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(static::all());
    }

    /**
     * The group a permission name belongs to, if it exists in the catalog.
     */
    public static function groupFor(string $name): ?string
    {
        foreach (static::groups() as $group => $permissions) {
            if (array_key_exists($name, $permissions)) {
                return $group;
            }
        }

        return null;
    }
}
