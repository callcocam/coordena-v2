<?php

namespace App\Jobs;

use App\Enums\CongregationIntroStatus;
use App\Models\CongregationIntro;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia a apresentação (primeiro contato) à congregação via WhatsApp Cloud.
 *
 * O template `coordena_intro` apresenta quem somos (coordenador +
 * congregação-casa), o link público da programação e a pergunta de opt-in com
 * os botões "Sim, aceito" / "Agora não". Sucesso grava o `wamid` na intro (é
 * ele que o IntroButtonHandler usa para correlacionar a resposta) e move
 * `pending → sent`; erro terminal da Meta (ou esgotar as tentativas) marca
 * `failed` — o reenvio é sempre uma nova intro.
 */
class SendCongregationIntro implements ShouldQueue
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
        public CongregationIntro $intro,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ResponsibleCoordinator $responsible): void
    {
        if ($this->intro->status !== CongregationIntroStatus::Pending) {
            return;
        }

        $congregation = $this->intro->congregation;
        $phone = Phone::normalize($congregation->contact_phone);

        if ($phone === null) {
            $this->fail(new CloudApiException('Congregation has no valid WhatsApp phone number.'));

            return;
        }

        $params = $this->templateParams($responsible);

        try {
            $result = WhatsApp::for($this->intro->team)->sendTemplate(
                $phone,
                TemplateMessage::make('intro', $params),
            );
        } catch (CloudApiException $exception) {
            if ($exception->isTerminal()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $this->intro->messages()->create([
            'direction' => 'outbound',
            'channel' => 'whatsapp',
            'body' => implode(' — ', $params),
            'wamid' => $result->messageId,
        ]);

        $this->intro->update([
            'status' => CongregationIntroStatus::Sent,
            'wamid' => $result->messageId,
            'sent_at' => now(),
        ]);
    }

    /**
     * The `coordena_intro` variables: coordinator, congregation (ours with
     * city) and public schedule link. Meta rejeita quebra de linha em variável.
     *
     * @return array<string, string>
     */
    protected function templateParams(ResponsibleCoordinator $responsible): array
    {
        $team = $this->intro->team;
        $home = $team->homeCongregation;
        $coordinator = $responsible->for($team);

        $congregation = $home?->name ?? $team->name;

        if ($home?->city !== null && $home->city !== '') {
            $congregation .= " ({$home->city})";
        }

        $params = [
            'coordinator' => $coordinator?->name ?? $team->name,
            'congregation' => $congregation,
            'link' => route('intro.portal', $this->intro->portal_token),
        ];

        return array_map(
            fn (string $param): string => trim(preg_replace('/\s+/u', ' ', $param) ?? ''),
            $params,
        );
    }

    /**
     * Mark the intro as failed once the job gives up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->intro->fresh()?->update([
            'status' => CongregationIntroStatus::Failed,
        ]);
    }
}
