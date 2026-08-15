---
paths:
  - 'app/Console/Commands/**'
  - app/Console/Commands/SendSpeakerReminders.php
---

# Commands

## Comandos agendados do PublicTalks: --dry-run e idempotência
Todos os comandos public-talks:* agendados suportam --dry-run (não despacha jobs, não grava nudged_at, não expira) e são idempotentes: reengate é único por send (guardado por nudged_at), expiração só transiciona sends `sent` além de PUBLIC_TALKS_EXPIRE_AFTER_DAYS. Agendamento vive em routes/console.php com withoutOverlapping()->onOneServer(); em produção roda no serviço `scheduler` do docker-compose (php artisan schedule:work). Novo comando agendado deve seguir esse contrato e ganhar linha no runbook docs/runbooks/public-talks-scheduler.md.

## Lembretes D-3/D-1/D-0 com dedupe por dia
public-talks:send-speaker-reminders roda três passadas: lembrete D-3 (speaker_days_before) e repique D-1 (speaker_second_days_before, PUBLIC_TALKS_SPEAKER_SECOND_REMINDER_DAYS) ao orador de QUALQUER direção com telefone+esboço, pulando Confirmed/NeedsReschedule; alerta D-0 (pending_days_before=0) ao coordenador responsável. Dedupe do lembrete é por dia (created_at >= hoje, kind=Reminder, Pending/Sent): D-3 não bloqueia D-1 e lembrete manual de hoje suprime o automático — não voltar para o dedupe "nunca recebeu reminder".
