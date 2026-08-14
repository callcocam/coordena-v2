<?php

namespace App\Services\PublicTalks\Inbound;

use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\PublicTalks\InboundDispatcherTest;

/**
 * Ponto ÚNICO de despacho do inbound de WhatsApp do módulo de discursos.
 *
 * Lição do v1: N listeners disputando a mensagem viraram fonte de bugs de
 * ordem. Aqui a precedência é declarada em {@see self::HANDLERS} — um array
 * só, testado como contrato ({@see InboundDispatcherTest})
 * — e o primeiro handler cujo `matches()` responder true trata a mensagem
 * sozinho. Quem for adicionar um handler precisa escolher a posição dele aqui.
 *
 * Cada mensagem é processada no máximo uma vez (idempotência por `wamid` via
 * `Cache::add`), então retries do webhook/fila não duplicam efeitos.
 */
class InboundDispatcher
{
    /**
     * A cadeia de precedência — a ORDEM IMPORTA:
     *
     * 1. Correlação por wamid (botões da notificação do orador). Nunca rotear
     *    botão só pelo rótulo.
     * 2. Correlação por wamid (botões da apresentação `coordena_intro`). Vem
     *    antes do PartnerReplyHandler porque a correlação explícita é mais
     *    forte que o casamento por telefone de um convite vivo, que capturaria
     *    a resposta do opt-in por engano.
     * 3. Correlação por wamid (botões do convite de troca/ajuda). Mesma
     *    razão do item 2: o `context_id` do quick reply é mais forte que o
     *    casamento por telefone do PartnerReplyHandler, que trataria o clique
     *    como texto livre de mesa.
     * 4. Conversa aberta do coordenador (fase 5 pluga aqui; hoje nunca casa).
     * 5. Congregação parceira com envio de convite vivo → mesa de trabalho.
     * 6. Orador conhecido com notificação viva → encaminhamento íntegro.
     * 7. Congregação `opted_out` sem fluxo vivo → proposta de reativação.
     * 8. Rede de segurança: não reconhecida → responsável, com throttle.
     *
     * @var list<class-string<InboundHandler>>
     */
    public const HANDLERS = [
        SpeakerButtonHandler::class,
        IntroButtonHandler::class,
        ExchangeInviteButtonHandler::class,
        CoordinatorConversationHandler::class,
        PartnerReplyHandler::class,
        SpeakerFreeTextHandler::class,
        ReactivationHandler::class,
        SafetyNetHandler::class,
    ];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Run the message through the handler chain, first match wins. A message
     * nobody claims is marked unhandled so the log shows it was looked at.
     */
    public function dispatch(WhatsAppInboundMessage $message): void
    {
        if (! Cache::add($this->idempotencyKey($message), true, now()->addWeek())) {
            return;
        }

        foreach (self::HANDLERS as $handlerClass) {
            /** @var InboundHandler $handler */
            $handler = $this->container->make($handlerClass);

            if ($handler->matches($message)) {
                $handler->handle($message);

                return;
            }
        }

        $message->markUnhandled();
    }

    /**
     * Cache key that makes each wamid processable only once.
     */
    protected function idempotencyKey(WhatsAppInboundMessage $message): string
    {
        return 'whatsapp-inbound:dispatched:'.$message->wamid;
    }
}
