<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeInviteKind;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;

/**
 * Monta o texto do convite de troca (semanas em falta + nossa lista de
 * oradores/temas do mês) — usado no envio manual agora e pelo WhatsApp na
 * fase 3. Formato multi-linha legível, herdado do v1.
 */
class InviteComposer
{
    public function __construct(
        protected ExchangeInviteManager $manager,
        protected SpeakerAvailability $availability,
    ) {}

    /**
     * The ready-to-copy invite text for the target congregation.
     */
    public function compose(ExchangeInvite $invite, Congregation $target): string
    {
        $team = $invite->team;
        $home = $team->homeCongregation;

        $lines = [
            __('app.public_talks.exchange.composer.greeting', [
                'congregation' => $target->name,
                'home' => $home?->name ?? $team->name,
                'month' => $invite->month->translatedFormat('F/Y'),
            ]),
            '',
            __('app.public_talks.exchange.composer.weeks_heading'),
        ];

        $meetingTime = $home?->meeting_time !== null
            ? substr($home->meeting_time, 0, 5)
            : null;

        foreach ($this->manager->openWeeks($invite) as $week) {
            $lines[] = $meetingTime !== null
                ? __('app.public_talks.exchange.composer.week_line_time', [
                    'date' => $week->date->translatedFormat('d/m (D)'),
                    'time' => $meetingTime,
                ])
                : __('app.public_talks.exchange.composer.week_line', [
                    'date' => $week->date->translatedFormat('d/m (D)'),
                ]);
        }

        if ($home !== null) {
            $speakers = $this->availability->availableFor($home, $invite->month);

            if ($speakers->isNotEmpty()) {
                $lines[] = '';
                $lines[] = __('app.public_talks.exchange.composer.speakers_heading', [
                    'month' => $invite->month->translatedFormat('F'),
                ]);

                foreach ($speakers as $speaker) {
                    $lines[] = $this->speakerLine($speaker);

                    foreach ($speaker->outlines as $outline) {
                        $lines[] = $this->outlineLine($outline);
                    }
                }
            }
        }

        $lines[] = '';
        $lines[] = __('app.public_talks.exchange.composer.closing');

        return implode("\n", $lines);
    }

    /**
     * The rich session message sent right after the partner accepts: only now
     * the open weeks, our speakers and the portal link travel — never before
     * (gate da melhoria 3).
     */
    public function acceptedSession(ExchangeInviteSend $send): string
    {
        $link = route('exchange.portal', ['portal_token' => $send->portal_token]);

        if ($send->kind === ExchangeInviteKind::Help) {
            return $this->helpSession($send, $link);
        }

        return $this->compose($send->invite, $send->congregation)
            ."\n\n".__('app.public_talks.exchange.accepted.portal_line', ['link' => $link]);
    }

    /**
     * Help variant: open weeks plus the direct-contact suggestion — the
     * coordinator may arrange with their own speakers and only register the
     * result in the portal.
     */
    protected function helpSession(ExchangeInviteSend $send, string $link): string
    {
        $invite = $send->invite;

        $lines = [
            __('app.public_talks.exchange.accepted.help_heading', [
                'congregation' => $send->congregation->name,
                'month' => $invite->month->translatedFormat('F/Y'),
            ]),
        ];

        foreach ($this->manager->openWeeks($invite) as $week) {
            $lines[] = __('app.public_talks.exchange.composer.week_line', [
                'date' => $week->date->translatedFormat('d/m (D)'),
            ]);
        }

        $lines[] = '';
        $lines[] = __('app.public_talks.exchange.accepted.help_direct', ['link' => $link]);

        return implode("\n", $lines);
    }

    /**
     * One speaker as `name - role: phone?`.
     */
    protected function speakerLine(Speaker $speaker): string
    {
        $line = sprintf(
            '%s - %s',
            $speaker->name,
            __('app.public_talks.speakers.roles.'.$speaker->role->value),
        );

        if ($speaker->phone !== null) {
            $line .= ': '.$speaker->phone;
        }

        return $line;
    }

    /**
     * One outline as `  number - title`.
     */
    protected function outlineLine(PublicTalkOutline $outline): string
    {
        return sprintf('  %d - %s', $outline->number, $outline->title);
    }
}
