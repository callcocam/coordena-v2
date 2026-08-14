<?php

namespace App\Services\PublicTalks;

use App\Models\Coordinator;
use App\Models\Team;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;

/**
 * Avisa o coordenador responsável (e ajudantes) sobre algo que aconteceu no
 * módulo de discursos: orador confirmou, convite de troca aceito, etc.
 *
 * Para cada destinatário de {@see ResponsibleCoordinator::recipientsFor()},
 * manda mensagem de sessão quando a janela de 24h dele está aberta (há inbound
 * recente daquele número) e cai no template `coordena_coordinator_alert`
 * quando não está. O envio é melhor-esforço: falha em um destinatário vira
 * warning no log e não impede os demais nem o fluxo que disparou o alerta.
 */
class CoordinatorAlert
{
    public function __construct(
        public ResponsibleCoordinator $responsibleCoordinator,
    ) {}

    /**
     * Send `$summary` (one short sentence, no line breaks) to every coordinator
     * of the team that can receive WhatsApp messages.
     */
    public function send(Team $team, string $summary): void
    {
        foreach ($this->responsibleCoordinator->recipientsFor($team) as $coordinator) {
            $this->sendTo($team, $coordinator, $summary);
        }
    }

    /**
     * Session text when the 24h window is open, template otherwise.
     */
    protected function sendTo(Team $team, Coordinator $coordinator, string $summary): void
    {
        $phone = Phone::normalize($coordinator->phone);

        if ($phone === null) {
            return;
        }

        try {
            if ($this->hasOpenWindow($phone)) {
                WhatsApp::for($team)->sendSessionText($phone, $this->sessionText($coordinator, $summary));

                return;
            }

            WhatsApp::for($team)->sendTemplate(
                $phone,
                TemplateMessage::make('coordinator_alert', [
                    'coordinator' => $coordinator->name,
                    'summary' => $this->singleLine($summary),
                ]),
            );
        } catch (CloudApiException $exception) {
            Log::warning('Coordinator alert not delivered.', [
                'team_id' => $team->id,
                'coordinator_id' => $coordinator->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * A Meta rejeita quebra de linha dentro de variável de template. Resumos
     * multi-linha (ex.: texto livre de orador encaminhado íntegro — só a
     * sessão preserva a formatação) são achatados para o template.
     */
    protected function singleLine(string $summary): string
    {
        return trim(preg_replace('/\s+/u', ' ', $summary) ?? $summary);
    }

    /**
     * Whether the coordinator wrote to us in the last 24h, keeping the
     * session window open for free-form text.
     */
    protected function hasOpenWindow(string $phone): bool
    {
        return WhatsAppInboundMessage::query()
            ->where('wa_id', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }

    /**
     * Free-form counterpart of the `coordena_coordinator_alert` template body.
     */
    protected function sessionText(Coordinator $coordinator, string $summary): string
    {
        return "Olá, *{$coordinator->name}*! Aviso do Coordena sobre os discursos públicos: {$summary}\n\n"
            .'Quando puder, abra a programação para conferir os detalhes.';
    }
}
