<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Speaker Reminders
    |--------------------------------------------------------------------------
    |
    | Antecedências (em dias) usadas pelo `public-talks:send-speaker-reminders`:
    | `speaker_days_before` é o primeiro lembrete ao orador (qualquer direção);
    | `speaker_second_days_before` é o repique a quem ainda não confirmou;
    | `pending_days_before` é o D-N do alerta de discursos ainda não
    | confirmados enviado ao coordenador responsável (0 = no dia).
    |
    */

    'reminders' => [
        'speaker_days_before' => env('PUBLIC_TALKS_SPEAKER_REMINDER_DAYS', 3),
        'speaker_second_days_before' => env('PUBLIC_TALKS_SPEAKER_SECOND_REMINDER_DAYS', 1),
        'pending_days_before' => env('PUBLIC_TALKS_PENDING_ALERT_DAYS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Invite Follow-up
    |--------------------------------------------------------------------------
    |
    | Prazos (em dias) usados pelo `public-talks:nudge-pending-invite-sends`:
    | após `nudge_after_days` sem resposta o envio recebe um único reengate;
    | após `expire_after_days` ele expira e o responsável é avisado para
    | convidar a próxima congregação do rodízio (envio segue manual).
    |
    */

    'exchange' => [
        'nudge_after_days' => env('PUBLIC_TALKS_NUDGE_AFTER_DAYS', 4),
        'expire_after_days' => env('PUBLIC_TALKS_EXPIRE_AFTER_DAYS', 10),
    ],

];
