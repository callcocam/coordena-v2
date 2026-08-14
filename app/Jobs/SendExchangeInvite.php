<?php

namespace App\Jobs;

use App\Enums\ExchangeInviteKind;
use App\Enums\ExchangeInviteSendStatus;
use App\Models\ExchangeInviteSend;
use App\Models\Speaker;
use App\Services\PublicTalks\Inbound\ExchangeInviteButtonHandler;
use App\Services\PublicTalks\SpeakerAvailability;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\InteractiveMessage;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Envia o abridor do convite mensal à congregação parceira via WhatsApp.
 *
 * O abridor é curtíssimo de propósito (economia Meta): informa QUANTOS
 * oradores temos ({@see SpeakerAvailability::availableFor}), nunca quem, e
 * não carrega link — o conteúdo rico só segue por sessão após o aceite.
 * Sem orador livre no mês a variante vira `coordena_exchange_help` (pedido
 * de ajuda). Com a janela de 24h aberta o convite vai como UMA mensagem
 * interativa de sessão (grátis), nunca um segundo template.
 *
 * Sucesso grava o `wamid` na mensagem outbound (correlação dos botões no
 * {@see ExchangeInviteButtonHandler}) e
 * move o send `pending → sent`; erro terminal da Meta (ou esgotar as
 * tentativas) marca `failed` — o reenvio é sempre uma nova linha de envio.
 */
class SendExchangeInvite implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public ExchangeInviteSend $send,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SpeakerAvailability $availability): void
    {
        if ($this->send->status !== ExchangeInviteSendStatus::Pending) {
            return;
        }

        $invite = $this->send->invite;
        $congregation = $this->send->congregation;
        $phone = Phone::normalize($congregation->contact_phone);

        if ($phone === null) {
            $this->fail(new CloudApiException('Congregation has no valid WhatsApp phone number.'));

            return;
        }

        $speakerCount = $this->availableSpeakers($availability)->count();
        $kind = $speakerCount === 0 ? ExchangeInviteKind::Help : ExchangeInviteKind::Exchange;
        $params = $this->templateParams($kind, $speakerCount);
        $body = $this->openerBody($kind, $params);

        try {
            $result = $this->hasOpenWindow($phone)
                ? WhatsApp::for($invite->team)->sendInteractive(
                    $phone,
                    new InteractiveMessage($body, [$kind->acceptLabel(), $kind->declineLabel()]),
                )
                : WhatsApp::for($invite->team)->sendTemplate(
                    $phone,
                    TemplateMessage::make($kind->templateKey(), $params),
                );

            $this->send->messages()->create([
                'direction' => 'outbound',
                'channel' => 'whatsapp',
                'body' => $body,
                'wamid' => $result->messageId,
            ]);
        } catch (CloudApiException $exception) {
            if ($exception->isTerminal()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $this->send->update([
            'kind' => $kind,
            'status' => ExchangeInviteSendStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Our speakers free to go out in the invite month (never exposed by name
     * in the opener — only the count travels).
     *
     * @return Collection<int, Speaker>
     */
    protected function availableSpeakers(SpeakerAvailability $availability): Collection
    {
        $home = $this->send->invite->team->homeCongregation;

        if ($home === null) {
            return new Collection;
        }

        return $availability->availableFor($home, $this->send->invite->month);
    }

    /**
     * The opener variables: contact, congregation (ours), month and — only on
     * the exchange variant — the speaker count phrase ("3 oradores"). Sem
     * link: ele só vai na sessão pós-aceite. Meta rejeita quebra de linha
     * dentro de variável.
     *
     * @return array<string, string>
     */
    protected function templateParams(ExchangeInviteKind $kind, int $speakerCount): array
    {
        $team = $this->send->invite->team;
        $congregation = $this->send->congregation;

        $params = [
            'contact' => $congregation->contact_name ?? $congregation->name,
            'congregation' => $team->homeCongregation?->name ?? $team->name,
            'month' => $this->send->invite->month->translatedFormat('F \d\e Y'),
        ];

        if ($kind === ExchangeInviteKind::Exchange) {
            $params['count'] = trans_choice('app.public_talks.exchange.opener.count', $speakerCount, [
                'count' => $speakerCount,
            ]);
        }

        return array_map(
            fn (string $param): string => trim(preg_replace('/\s+/u', ' ', $param) ?? ''),
            $params,
        );
    }

    /**
     * The opener text, mirroring the approved template body — reused as the
     * session message when the 24h window is already open and stored as the
     * outbound `exchange_message`.
     *
     * @param  array<string, string>  $params
     */
    protected function openerBody(ExchangeInviteKind $kind, array $params): string
    {
        $key = $kind === ExchangeInviteKind::Exchange
            ? 'app.public_talks.exchange.opener.exchange'
            : 'app.public_talks.exchange.opener.help';

        return __($key, $params);
    }

    /**
     * Whether the contact wrote to us in the last 24h, keeping the session
     * window open for the free interactive opener.
     */
    protected function hasOpenWindow(string $phone): bool
    {
        return WhatsAppInboundMessage::query()
            ->where('wa_id', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }

    /**
     * Mark the send as failed once the job gives up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->send->fresh()?->update([
            'status' => ExchangeInviteSendStatus::Failed,
        ]);
    }
}
