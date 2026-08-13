# Fase 6 — Scheduler: automação do ciclo

Depende de: fases 3 e 4 (pode rodar em paralelo à 5). Tamanho: M.
**Leia antes: `00-INDICE.md`**.

## Lição do v1

Os comandos foram escritos, mas o scheduler **nunca foi ativado em produção** — ficou como
pendência eterna de runbook. Aqui, **rodando em produção é critério de aceite da fase**:
a fase só fecha com evidência de execução real agendada.

## Comandos (`app/Console/Commands/`, agendados em `routes/console.php`, todos idempotentes,
`withoutOverlapping()`, com `--dry-run`, jitter entre envios de WhatsApp)

### 6.1 `public-talks:ensure-horizon` — diário
- [ ] Para cada time com módulo configurado (casa + responsável): `ScheduleHorizon::ensure()`.
  Regra do produto: horizonte contínuo de 3 meses — quando restam 2 meses preenchidos,
  o mês seguinte é gerado. Deixa de depender de visita à tela.
- [ ] Garante também o `exchange_invite` de cada mês novo com semanas em falta
  (`ExchangeInviteManager::forMonth`).

### 6.2 `public-talks:send-speaker-reminders` — diário
- [ ] D-3: assignment `home` com orador com telefone, sem `kind=reminder` enviado → template
  `coordena_talk_reminder`.
- [ ] D-1: discursos do fim de semana ainda não `confirmed` → alerta único ao responsável/
  ajudantes listando as pendências.
- [ ] Antecedências em `config/public_talks.php` (defaults 3 e 1); por-time fica para depois.

### 6.3 `public-talks:nudge-pending-invite-sends` — diário
- [ ] `exchange_invite_send` `sent` sem resposta há ≥ N dias (config, default 4): **1 reengate
  único** por send (sessão se janela aberta; senão `coordena_coordinator_alert`-like curto,
  reaproveitando template aprovado). Registrado em `exchange_messages` outbound.
- [ ] Após M dias (default 10): send `expired` + aviso ao responsável sugerindo enviar o
  convite à próxima congregação do rodízio (o envio continua **manual** — decisão humana).

## Fora de escopo (registrado)

- Envio **automático** de convite à próxima congregação (o produto hoje quer envio manual;
  se um dia entrar, será flag opt-in por time, default OFF).

## Operacional (parte da fase, não pendência)

- [ ] Runbook curto em `docs/runbooks/` (como no v1): ativação do scheduler no ambiente de
  produção do v2 (supervisor/cron/container conforme o deploy deste projeto — ver
  `vps-deployment/`/`docker/`), ensaio com `--dry-run`, checagem de `failed_jobs` pós-janela.
- [ ] **Executar a ativação** e colar a evidência (log da primeira execução real) no runbook.

## Testes

- [ ] Um Feature test por comando: idempotência (2× não duplica), janelas D-3/D-1, reengate
  único, expiração + aviso, `--dry-run` não despacha nada. `Queue::fake()`/`Bus::fake()`.

Rodar `vendor/bin/pint --dirty --format agent`.

## Critério de aceite

- Scheduler ativo em produção: horizonte avança sozinho; convite do mês novo nasce sozinho;
  orador recebe D-3; responsável recebe pendências D-1; send parado recebe 1 reengate e expira
  com aviso. Evidência real registrada no runbook.
