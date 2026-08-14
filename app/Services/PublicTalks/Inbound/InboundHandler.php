<?php

namespace App\Services\PublicTalks\Inbound;

use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

/**
 * Um tratador de mensagem inbound de WhatsApp na cadeia do
 * {@see InboundDispatcher}. A ordem entre os handlers é declarada num único
 * lugar ({@see InboundDispatcher::HANDLERS}) e o primeiro cujo `matches()`
 * responder true trata a mensagem sozinho — não existem listeners concorrentes.
 */
interface InboundHandler
{
    /**
     * Whether this handler recognizes the message. Must be side-effect free:
     * the dispatcher may probe several handlers before one claims the message.
     */
    public function matches(WhatsAppInboundMessage $message): bool;

    /**
     * Process the message. Responsible for recording the outcome on the row
     * itself ({@see WhatsAppInboundMessage::markForwarded()} /
     * {@see WhatsAppInboundMessage::markUnhandled()}).
     */
    public function handle(WhatsAppInboundMessage $message): void;
}
