<?php

namespace App\Jobs;

use App\Enums\ExchangeInviteSendStatus;
use App\Models\ExchangeInviteSend;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reengate único de um convite de troca `sent` sem resposta: texto de
 * sessão quando a janela de 24h está aberta, senão o template aprovado
 * `coordena_coordinator_alert` com um resumo curto. Sempre registrado como
 * mensagem outbound em `exchange_messages`. O comando marca `nudged_at` na
 * despachada, então o send nunca recebe um segundo reengate — falha aqui é
 * só logada, sem mudar o status do send.
 */
class SendExchangeInviteNudge implements ShouldQueue
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
    public function handle(): void
    {
        if ($this->send->status !== ExchangeInviteSendStatus::Sent) {
            return;
        }

        $congregation = $this->send->congregation;
        $phone = Phone::normalize($congregation->contact_phone);

        if ($phone === null) {
            return;
        }

        $body = $this->body();

        try {
            $result = $this->deliver($phone, $body);
        } catch (CloudApiException $exception) {
            if ($exception->isTerminal()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $this->send->messages()->create([
            'direction' => 'outbound',
            'channel' => 'whatsapp',
            'body' => $body,
            'wamid' => $result->messageId,
        ]);
    }

    /**
     * Session text inside the 24h window, `coordinator_alert` template otherwise.
     */
    protected function deliver(string $phone, string $body): SendResult
    {
        $team = $this->send->invite->team;

        if ($this->hasOpenWindow($phone)) {
            return WhatsApp::for($team)->sendSessionText($phone, $body);
        }

        return WhatsApp::for($team)->sendTemplate(
            $phone,
            TemplateMessage::make('coordinator_alert', [
                'coordinator' => $this->contactName(),
                'summary' => trim(preg_replace('/\s+/u', ' ', $this->summary()) ?? $this->summary()),
            ]),
        );
    }

    /**
     * The free-form nudge text used when the session window is open.
     */
    protected function body(): string
    {
        return "Olá, *{$this->contactName()}*! {$this->summary()}\n\n"
            .'Se preferir, é só responder por aqui mesmo.';
    }

    /**
     * Short reminder about the still unanswered invite.
     */
    protected function summary(): string
    {
        $invite = $this->send->invite;
        $congregation = $invite->team->homeCongregation?->name ?? $invite->team->name;
        $month = $invite->month->translatedFormat('F \d\e Y');

        return "O convite de troca de {$month} da congregação {$congregation} ainda aguarda resposta: "
            .route('exchange.portal', $this->send->portal_token);
    }

    /**
     * Get the contact name of the invited congregation.
     */
    protected function contactName(): string
    {
        $congregation = $this->send->congregation;

        return $congregation->contact_name ?? $congregation->name;
    }

    /**
     * Whether the contact wrote to us in the last 24h, keeping the session
     * window open for the free-form text.
     */
    protected function hasOpenWindow(string $phone): bool
    {
        return WhatsAppInboundMessage::query()
            ->where('wa_id', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }

    /**
     * Only log when the nudge gives up — the send keeps its status.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Exchange invite nudge not delivered.', [
            'send_id' => $this->send->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
