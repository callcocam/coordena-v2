<?php

namespace App\Services\PublicTalks\Conversation;

/**
 * O que um estado da conversa quer mostrar: um corpo de texto e as opções
 * selecionáveis (chave estável => rótulo curto, ≤24 chars por limite da Meta).
 */
final class Prompt
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public readonly string $body,
        public readonly array $options = [],
    ) {}
}
