# Runbook — Scheduler do módulo de discursos (arranjo de oradores)

## O que roda

Agendado em `routes/console.php` (timezone de `config('app.timezone')`):

| Horário | Comando | Efeito |
| --- | --- | --- |
| 05:00 | `public-talks:ensure-horizon` | Avança o horizonte de convites de permuta (convite do mês novo nasce sozinho). |
| 08:00 | `public-talks:send-speaker-reminders` | Lembrete D-3 ao orador + pendências D-1 ao coordenador responsável. |
| 09:00 | `public-talks:nudge-pending-invite-sends` | 1 reengate único por send `sent` sem resposta (≥ `PUBLIC_TALKS_NUDGE_AFTER_DAYS`, default 4) e expiração + aviso ao responsável (≥ `PUBLIC_TALKS_EXPIRE_AFTER_DAYS`, default 10). O envio à próxima congregação continua manual. |

Todos usam `withoutOverlapping()->onOneServer()`.

## Ativação em produção

O deploy é via Docker Compose (`docker-compose.yml`). O serviço `scheduler` roda
`php artisan schedule:work` reaproveitando a imagem do `php` (mesmo padrão do
serviço `worker`).

```bash
docker compose up -d scheduler
docker compose logs -f scheduler   # deve logar as execuções nos horários acima
```

Alternativa sem o serviço (cron do host):

```cron
* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1
```

## Ensaio antes da primeira janela real

```bash
php artisan schedule:list                                  # confere horários/timezone
php artisan public-talks:ensure-horizon
php artisan public-talks:send-speaker-reminders --dry-run  # só lista, não despacha
php artisan public-talks:nudge-pending-invite-sends --dry-run
```

`--dry-run` não despacha jobs, não marca `nudged_at` e não expira nada.

## Checagem pós-janela

```bash
docker compose logs scheduler | tail -50
php artisan tinker --execute 'echo Illuminate\Support\Facades\DB::table("failed_jobs")->count();'
```

Esperado: `failed_jobs` = 0; sends reengatados com `nudged_at` preenchido; sends
antigos com `status = expired` e alerta registrado em `exchange_messages`.

## Evidência da primeira execução real

> **Pendente** — colar aqui o trecho de `docker compose logs scheduler` da
> primeira janela real (05:00–09:00) após ativar em produção.
