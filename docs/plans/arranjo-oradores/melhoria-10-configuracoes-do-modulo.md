# Melhoria 10 — Tela de configurações do módulo de discursos (por time)

> Plano autossuficiente para ser executado em um **chat separado**. Antes de codar: leia
> `00-INDICE.md`, este arquivo e as regras em `.ai/rules/` (controllers →
> `controllers.md` + `app.md`; serviços → `services.md` + `public-talks.md`; telas →
> `pages.md` + `js.md`; testes → `tests.md`; textos → `whatsapp-templates.md`, que vale
> para `lang/**`: **"troca(s)", nunca "permuta(s)"**). Rode `grep -rin 'keyword' .ai/rules`
> para o resto. Siga o CLAUDE.md (Boost `search-docs`, Pint, Pest, Wayfinder, Inertia v3 + Vue).

## Pedido do dono (2026-08-16, literal)

"na verssão do coordena tem essa tela de configuração, seria bom ter essa configuração.
pra não deixar enxessado a configuração do controlador, crie um plano pra executar em
outro chat, pode dividir em mais de um app/Http/Controllers/PublicTalks"

Contexto do pedido: prazos e comportamentos do módulo hoje só mudam por `.env`/config
global — o coordenador não tem onde ajustar lembretes, reengates etc. No v1
(`~/projects/coordena`) existia `PublicTalks/PublicTalkSettingsController` +
`Services/PublicTalks/PublicTalkSettings` com overrides por congregação-casa e fallback
para `config/public_talks.php`. Queremos o equivalente no v2, **por time**, e aproveitar
para **enxugar o `ScheduleController`** movendo as ações de notificação para um
controller próprio.

## Estado atual (verificado em 2026-08-16 — reconfira antes de editar)

- `config/public_talks.php` (global, via env):
  - `reminders.speaker_days_before` (default 3), `reminders.speaker_second_days_before`
    (default 1), `reminders.pending_days_before` (default 0).
  - `exchange.nudge_after_days` (default 4), `exchange.expire_after_days` (default 10).
- Consumidores: `SendSpeakerReminders` (lê os 3 de `reminders.*` com `config()` **uma vez
  para todos os times**), `NudgePendingInviteSends` (lê `exchange.*` idem).
- Horizonte: `ScheduleHorizon::MONTHS_AHEAD = 3` (const, também usada em
  `ExchangeRoundRobin` e `EnsurePublicTalksHorizon`).
- Scheduler (`routes/console.php`): `ensure-horizon` 05:00, `send-speaker-reminders`
  08:00, `nudge-pending-invite-sends` 09:00.
- `ScheduleController` (409 linhas) concentra a tela da programação **e** as ações
  `notify` / `notifyExchange` / `notifyError` (rotas
  `public-talks.schedule.notify` e `public-talks.schedule.notify-exchange` em
  `routes/web.php:37-38`).
- Não existe nenhuma tela de configurações do módulo no v2; `SetupController` cuida só
  do wizard inicial (congregação-casa + WhatsApp).
- v1 de referência: `~/projects/coordena/app/Services/PublicTalks/PublicTalkSettings.php`
  (CONFIG_KEYS: `horizon_months`, `speaker_reminder_days`, `coordinator_alert_days`,
  `exchange_nudge_days`, `exchange_expire_days`, `exchange_max_per_run`) e
  `SavePublicTalkSettingsRequest` (validações, ex.: `expire > nudge`).
- Rotas do grupo do time têm prefixo `{current_team}` → todo método precisa de
  `string $current_team` posicional antes do model binding (regra `controllers.md`).

## Diagnóstico relacionado (contexto, não é escopo de código)

O "Enviar / lembrar" **funciona**: o clique despacha `SendSpeakerAssignmentNotification`,
mas os jobs falham com `Speaker has no valid WhatsApp phone number` porque os oradores
do seed estão sem telefone (`speakers.phone` vazio). Fix é de **dados** (cadastrar
telefone do orador). A Fase D abaixo só melhora o feedback disso na UI.

## Escopo

### Fase A — Serviço de settings por time

1. Migration: coluna `public_talk_settings` (jsonb, nullable) em `teams`. Guarda **só
   os overrides** — chave ausente/null = usa o default global.
2. Serviço `app/Services/PublicTalks/PublicTalkSettings.php` (inspirado no v1):
   - Chaves da v2 (nome da chave → fallback em `config/public_talks.php`):
     - `speaker_reminder_days` → `reminders.speaker_days_before`
     - `speaker_second_reminder_days` → `reminders.speaker_second_days_before`
     - `pending_alert_days` → `reminders.pending_days_before`
     - `exchange_nudge_days` → `exchange.nudge_after_days`
     - `exchange_expire_days` → `exchange.expire_after_days`
   - API: `for(Team $team)->get(string $key): int`, `all(): array` (valores efetivos),
     `overrides(): array`, `save(array $overrides)` (remove chaves iguais ao default
     para não fossilizar o default no banco). `assertKnown($key)` lança em chave
     desconhecida.
   - **Não** incluir `horizon_months` nesta melhoria: `MONTHS_AHEAD` é const usada em
     3 lugares e mexe com geração de convite/rodízio — risco alto, ganho baixo. Se o
     dono pedir depois, vira melhoria própria.
3. Trocar as leituras de `config()` nos comandos para o serviço, **por time** dentro do
   loop de times:
   - `SendSpeakerReminders`: offsets e `pendingDate` passam a ser resolvidos por time.
   - `NudgePendingInviteSends`: thresholds por time (atenção: hoje a query é global —
     particionar por `team_id` ou resolver o threshold por send via time do invite).

### Fase B — Controllers (dividir, não inchar)

1. **Novo** `app/Http/Controllers/PublicTalks/SettingsController.php`:
   - `show(Request $request, string $current_team)`: renderiza
     `publicTalks/Settings` com `settings` (efetivos), `defaults` (globais) e
     `overrides` (o que o time customizou), + `canManage`.
   - `update(SavePublicTalkSettingsRequest $request, string $current_team)`: grava via
     serviço, flash toast de sucesso, `back()`.
   - FormRequest `app/Http/Requests/PublicTalks/SavePublicTalkSettingsRequest.php`
     (espelhar limites do v1): inteiros `nullable` (`null` = voltar ao padrão),
     `speaker_reminder_days` 0–30, `speaker_second_reminder_days` 0–30 (e `<` que o
     primeiro lembrete), `pending_alert_days` 0–30, `exchange_nudge_days` 1–60,
     `exchange_expire_days` 1–90 **e maior que** `exchange_nudge_days` (mensagem
     dedicada em lang, como no v1).
   - Autorização: mesma gate/policy que protege a gestão da programação (ver como
     `canManage` é montado no `ScheduleController` e reutilizar).
2. **Extrair** de `ScheduleController` os métodos `notify`, `notifyExchange` e
   `notifyError` para um novo
   `app/Http/Controllers/PublicTalks/ScheduleNotificationController.php`
   (mesma assinatura, mesmas rotas — só muda o controller em `routes/web.php`).
   `ScheduleController` fica só com a tela. Não mudar comportamento.
3. Rotas (grupo do time, `routes/web.php`):
   - `GET discursos/configuracoes` → `SettingsController@show`
     (`public-talks.settings.show`)
   - `PUT discursos/configuracoes` → `SettingsController@update`
     (`public-talks.settings.update`)
   - Rodar `php artisan wayfinder:generate` depois de mexer nas rotas.

### Fase C — Tela `publicTalks/Settings.vue`

1. Página Inertia mobile-first (cartões empilhados; seguir `pages.md` e o padrão visual
   de `Setup.vue`/`Schedule.vue`, `PageContainer` com `back-href` para o dashboard).
2. Dois blocos:
   - **Lembretes ao orador**: 1º lembrete (dias antes), repique (dias antes), alerta de
     pendências ao coordenador (dias antes, 0 = no dia).
   - **Acompanhamento das trocas**: reengate após N dias sem resposta, expiração após
     N dias.
3. Cada campo mostra o **padrão do sistema** como placeholder/hint ("Padrão: 3 dias");
   campo vazio = usar o padrão. Botão "Restaurar padrões" limpa os overrides.
4. Entrada da tela: link/ação "Configurações" na tela da programação (`Schedule.vue`,
   junto das actions do header, visível só com `canManage`).
5. i18n: todas as strings em `lang/pt_BR/app/public_talks.php` (nova seção `settings`).
   Lembrar: "troca(s)", nunca "permuta(s)".

### Fase D — Feedback de envio bloqueado (pequena, aproveita o embalo)

1. `Schedule.vue` já recebe `notifiable` por slot e mostra "Sem telefone para WhatsApp".
   Garantir que o botão "Enviar / lembrar" da semana de troca fique **desabilitado com
   tooltip/hint do motivo** quando `exchangeNotifiable(group)` está vazio, em vez de
   abrir o diálogo e falhar no backend (hoje o clique abre confirmação e o backend
   responde com toast de erro — confuso, foi o que gerou o report do dono).
2. Nenhuma mudança de backend nesta fase (as validações do
   `ScheduleNotificationController` continuam como estão, são a rede de segurança).

## Testes (Pest, `php artisan test --compact --filter=...`)

1. `tests/Unit/PublicTalkSettingsTest`: fallback para config, override por time,
   `save()` descarta valores iguais ao default, chave desconhecida lança.
2. `tests/Feature/PublicTalks/SettingsControllerTest`: show exige membro/gestor do time,
   update valida limites (`expire <= nudge` falha; segundo lembrete >= primeiro falha),
   `null` limpa override, team scope (time A não enxerga/edita override do time B).
3. `tests/Feature/.../SendSpeakerRemindersTest` e `NudgePendingInviteSendsTest`: já
   existem? Se sim, **adicionar caso** com override por time (dois times com prazos
   diferentes na mesma execução); se não, criar o caso mínimo.
4. Testes existentes das rotas de notify continuam passando após a extração do
   controller (rodar o filtro dos testes de Schedule/notify).
5. `vendor/bin/pint --dirty --format agent` antes de finalizar.

## Critérios de aceite

- Coordenador ajusta os 5 prazos pela tela, por time, sem tocar em `.env`; vazio volta
  ao padrão global e a tela deixa claro qual é o padrão.
- Comandos agendados respeitam o override de cada time na mesma execução.
- `ScheduleController` sem os métodos de notificação; rotas `notify`/`notify-exchange`
  inalteradas para o front (mesmos nomes → wayfinder regenerado sem quebra).
- Botão de envio da semana de troca não abre confirmação quando ninguém está apto —
  mostra o motivo.
- Tudo verde: testes novos + suíte afetada; zero texto fixo fora de `lang/`.

## Fora de escopo (registrar, não fazer)

- `horizon_months` configurável (ver Fase A.2).
- Horários do scheduler por time (05:00/08:00/09:00 continuam globais).
- Configuração de credenciais WhatsApp (já coberta pelo Setup/termos).
