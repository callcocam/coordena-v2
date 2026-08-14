<?php

namespace App\Listeners;

use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\Events\WhatsAppMessageReceived;
use Callcocam\WhatsAppCloud\Listeners\StoreInboundMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ponte entre o webhook do pacote e o {@see InboundDispatcher} do app.
 *
 * O pacote persiste a mensagem de forma síncrona no request do webhook
 * ({@see StoreInboundMessage}); este
 * listener roda depois, na fila, carrega a linha persistida pelo `wamid` e
 * entrega ao dispatcher — o ÚNICO ponto de decisão do inbound (fase 4).
 */
class DispatchInboundWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected InboundDispatcher $dispatcher,
    ) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        $wamid = $event->message['id'] ?? null;

        if (! is_string($wamid) || $wamid === '') {
            return;
        }

        $message = WhatsAppInboundMessage::query()->where('wamid', $wamid)->first();

        if ($message === null) {
            return;
        }

        $this->dispatcher->dispatch($message);
    }
}
