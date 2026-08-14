<?php

namespace App\Console\Commands;

use App\Enums\ExchangeInviteSendStatus;
use App\Jobs\SendExchangeInviteNudge;
use App\Models\ExchangeInviteSend;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ExchangeRoundRobin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reengate único dos convites de troca `sent` sem resposta há mais de
 * `public_talks.nudge_after_days` dias, e expiração dos que passaram de
 * `public_talks.expire_after_days`. Marca `nudged_at` já na despachada, então
 * cada send recebe no máximo um reengate mesmo se o comando rodar de novo
 * antes de a fila processar; a expiração usa a própria mudança de status como
 * guarda de idempotência. O envio à próxima congregação continua manual — o
 * comando só avisa o coordenador responsável sugerindo o próximo do rodízio.
 */
#[Signature('public-talks:nudge-pending-invite-sends {--dry-run : Só lista o que seria reengatado/expirado, sem despachar nada}')]
#[Description('Reengata (uma única vez) e expira convites de troca enviados e ainda sem resposta')]
class NudgePendingInviteSends extends Command
{
    public function __construct(
        public ExchangeRoundRobin $roundRobin,
        public CoordinatorAlert $coordinatorAlert,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->expire($dryRun);
        $this->nudge($dryRun);

        return self::SUCCESS;
    }

    /**
     * Reengate único dos sends `sent` sem resposta e ainda sem `nudged_at`.
     */
    protected function nudge(bool $dryRun): void
    {
        $threshold = now()->subDays((int) config('public_talks.exchange.nudge_after_days'));
        $expireThreshold = now()->subDays((int) config('public_talks.exchange.expire_after_days'));

        $sends = ExchangeInviteSend::query()
            ->where('status', ExchangeInviteSendStatus::Sent)
            ->whereNull('nudged_at')
            ->where('created_at', '<=', $threshold)
            ->where('created_at', '>', $expireThreshold)
            ->with(['congregation', 'invite.team'])
            ->get();

        foreach ($sends->values() as $index => $send) {
            /** @var ExchangeInviteSend $send */
            if ($dryRun) {
                $this->line("[dry-run] reengataria o convite para {$send->congregation->name} (send #{$send->id}).");

                continue;
            }

            $send->forceFill(['nudged_at' => now()])->save();

            SendExchangeInviteNudge::dispatch($send)->delay($index * random_int(5, 15));

            $this->info("Reengate despachado para {$send->congregation->name} (send #{$send->id}).");
        }
    }

    /**
     * Expira sends `sent` sem resposta há `expire_after_days` e avisa o
     * coordenador responsável sugerindo a próxima congregação do rodízio.
     */
    protected function expire(bool $dryRun): void
    {
        $threshold = now()->subDays((int) config('public_talks.exchange.expire_after_days'));

        $sends = ExchangeInviteSend::query()
            ->where('status', ExchangeInviteSendStatus::Sent)
            ->where('created_at', '<=', $threshold)
            ->with(['congregation', 'invite.team'])
            ->get();

        foreach ($sends as $send) {
            /** @var ExchangeInviteSend $send */
            if ($dryRun) {
                $this->line("[dry-run] expiraria o convite para {$send->congregation->name} (send #{$send->id}).");

                continue;
            }

            $send->forceFill(['status' => ExchangeInviteSendStatus::Expired])->save();

            $invite = $send->invite;
            $next = $this->roundRobin->nextFor($invite);

            $summary = sprintf(
                'Convite de troca para %s expirou sem resposta (%s).%s',
                $send->congregation->name,
                $invite->month->translatedFormat('F/Y'),
                $next !== null
                    ? " Sugestão: enviar o convite para {$next->name}, próxima do rodízio."
                    : ' Não há próxima congregação disponível no rodízio.',
            );

            $this->coordinatorAlert->send($invite->team, $summary);

            $this->info("Convite para {$send->congregation->name} expirado (send #{$send->id}); responsável avisado.");
        }
    }
}
