<?php

namespace App\Jobs;

use App\Enums\ExchangeInviteSendStatus;
use App\Models\ExchangeInviteSend;
use App\Services\PublicTalks\InviteComposer;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia o convite de permuta à congregação parceira via WhatsApp Cloud.
 *
 * O template `coordena_exchange_invite` é o abridor (mês + link do portal);
 * quando a janela de 24h do contato está aberta, o texto rico do
 * {@see InviteComposer} segue como mensagem de sessão logo atrás. Sucesso
 * grava o `wamid` na mensagem outbound e move o send `pending → sent`; erro
 * terminal da Meta (ou esgotar as tentativas) marca o send `failed` — o
 * reenvio é sempre uma nova linha de envio.
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
    public function handle(InviteComposer $composer): void
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

        $body = $composer->compose($invite, $congregation);

        try {
            $result = WhatsApp::for($invite->team)->sendTemplate(
                $phone,
                TemplateMessage::make('exchange_invite', $this->templateParams()),
            );

            $this->send->messages()->create([
                'direction' => 'outbound',
                'channel' => 'whatsapp',
                'body' => $body,
                'wamid' => $result->messageId,
            ]);

            if ($this->hasOpenWindow($phone)) {
                WhatsApp::for($invite->team)->sendSessionText($phone, $body);
            }
        } catch (CloudApiException $exception) {
            if ($exception->isTerminal()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $this->send->update([
            'status' => ExchangeInviteSendStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * The `coordena_exchange_invite` variables: contact, congregation (ours),
     * month and portal link. Meta rejeita quebra de linha dentro de variável.
     *
     * @return array<string, string>
     */
    protected function templateParams(): array
    {
        $team = $this->send->invite->team;
        $congregation = $this->send->congregation;

        $params = [
            'contact' => $congregation->contact_name ?? $congregation->name,
            'congregation' => $team->homeCongregation?->name ?? $team->name,
            'month' => $this->send->invite->month->translatedFormat('F \d\e Y'),
            'link' => route('exchange.portal', $this->send->portal_token),
        ];

        return array_map(
            fn (string $param): string => trim(preg_replace('/\s+/u', ' ', $param) ?? ''),
            $params,
        );
    }

    /**
     * Whether the contact wrote to us in the last 24h, keeping the session
     * window open for the free-form rich text.
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
