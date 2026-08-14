<?php

namespace App\Support;

use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use Callcocam\WhatsAppCloud\Contracts\WhatsAppCredentials;
use Callcocam\WhatsAppCloud\Contracts\WhatsAppCredentialsResolver;
use Callcocam\WhatsAppCloud\Support\ArrayCredentials;

/**
 * Resolve um Team para as credenciais do WhatsApp Cloud: a conexão própria do
 * time ({@see TeamWhatsappConnection}) quando conectada, senão o
 * número compartilhado de `whatsapp-cloud.default` (dev/sandbox), senão null —
 * e o pacote reporta "não configurado".
 */
class TeamCredentialsResolver implements WhatsAppCredentialsResolver
{
    public function resolve(mixed $context): ?WhatsAppCredentials
    {
        if ($context instanceof WhatsAppCredentials) {
            return $context;
        }

        if ($context instanceof Team) {
            $connection = $context->whatsappConnection;

            if ($connection?->isConnected()) {
                return $connection;
            }
        }

        return ArrayCredentials::fromArray((array) config('whatsapp-cloud.default'));
    }
}
