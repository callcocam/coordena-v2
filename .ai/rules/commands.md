---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Comandos agendados do PublicTalks: --dry-run e idempotência
Todos os comandos public-talks:* agendados suportam --dry-run (não despacha jobs, não grava nudged_at, não expira) e são idempotentes: reengate é único por send (guardado por nudged_at), expiração só transiciona sends `sent` além de PUBLIC_TALKS_EXPIRE_AFTER_DAYS. Agendamento vive em routes/console.php com withoutOverlapping()->onOneServer(); em produção roda no serviço `scheduler` do docker-compose (php artisan schedule:work). Novo comando agendado deve seguir esse contrato e ganhar linha no runbook docs/runbooks/public-talks-scheduler.md.
