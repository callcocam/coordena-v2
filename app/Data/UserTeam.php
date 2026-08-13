<?php

namespace App\Data;

readonly class UserTeam
{
    /**
     * @param  list<array{key: string, name: string}>  $cargos
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public bool $isOwner,
        public array $cargos,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
