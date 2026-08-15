# Melhoria 8 — Tela da programação do mês enriquecida (Schedule)

> Plano autossuficiente para ser executado em um **chat separado**. Antes de codar: leia
> `00-INDICE.md`, este arquivo inteiro e as regras em `.ai/rules/` (a tela é
> `resources/js/pages/publicTalks/Schedule.vue` → `pages.md`; o controller é
> `app/Http/Controllers/PublicTalks/ScheduleController.php` → `controllers.md` + `app.md`;
> testes → `tests.md`). Rode `grep -rin 'keyword' .ai/rules` para o que o glob não pega.

## Pedido do dono (2026-08-14, literal)

"a tela da programação do mês, por exemplo não diz qual congregação e o orador, não diz o
nome de quem vai sair, teria q ter um botão pra enviar uma mensagem, pra ele confirmar, ou
pedir pra remarcar, no outro tinha isso. outra coisa: a programação tá completa, então
deveria esconder o botão convidar congregação no mês fechado. em fim, temos que enriquecer
essa tela."

## Estado atual (verificado no código em 2026-08-14 — reconfira antes de editar)

- Tela: `resources/js/pages/publicTalks/Schedule.vue` (~475 linhas). Cards por semana com
  data, badge de tipo (`home|incoming|outgoing`), badge de status e sheet de edição
  (orador + esboço) só para semanas `home` editáveis.
- Backend: `ScheduleController@weeksFor` **já envia** `counterpart` (nome da congregação
  parceira via `counterpartCongregation:id,name`), `speaker {id,name}` e
  `outline {id,number,title}` para TODOS os tipos. O tipo TS `ScheduleWeek`
  (`resources/js/types/publicTalks.ts`) já tem `counterpart: string | null`.
- Lacunas de exibição (o dado existe, o template esconde):
  - Card `outgoing`: mostra SÓ `week.counterpart` — **não mostra o orador que sai**
    (`week.speaker`) nem o tema.
  - Card `incoming`: mostra orador + tema — **não mostra a congregação de origem**
    (`week.counterpart`).
- Mensagem/confirmação: `ScheduleController@notify` já enfileira
  `SendSpeakerAssignmentNotification::queueFor($assignment, SpeakerNotificationKind::Assignment, ...)`
  — mas está `abort_unless(... type === Home)` e o botão só existe dentro do sheet.
  O fluxo de resposta **já existe ponta a ponta**:
  `app/Services/PublicTalks/Inbound/SpeakerButtonHandler.php` já grava
  `SpeakerNotificationStatus::Confirmed|RescheduleRequested` e muda o
  `TalkAssignmentStatus` para `Confirmed|NeedsReschedule`. `SpeakerNotificationKind`
  tem `Assignment` (template `talk_assignment`) e `Reminder` (`talk_reminder`).
- Botão "Convidar congregação" (`invite_congregation`, `goToExchange`) aparece sempre que
  `canManage`, mesmo com o mês completo.
- Badge pendências: `pending_badge => ':count pendente|:count pendentes'` — conferir se o
  helper `t()` do front resolve o plural `|`; na UI apareceu cru ("6 pendente|6 pendentes").

## Escopo

### Fase A — Backend (payload + regras)

1. `weeksFor`: enriquecer cada item com o que a UI precisa (mantendo nomes descritivos):
   - `speaker.phone` (para exibir/validar botão de mensagem; já é carregado no `with`).
   - Estado da última notificação da semana: `notification` =
     `{ kind, status, sent_at } | null` (consultar o modelo de notificações do orador
     usado por `SendSpeakerAssignmentNotification` / `SpeakerButtonHandler`).
   - `notifiable: bool` — regra de servidor: tem orador + esboço + telefone normalizável
     (`Phone::normalize`) + `canSendWhatsappApi()` + tipo permitido.
2. Ampliar `notify` para além de `Home`: permitir também `Outgoing` quando o orador é
   nosso (é "o nome de quem vai sair" — a confirmação é com ele). `Incoming` fica fora
   (o orador é da parceira; o canal é o coordenador dela — registrar como evolução futura
   se o dono pedir). Ajustar o `abort_unless` e o Gate/policy correspondente.
3. Nova ação "lembrete/confirmar" reutilizando `SpeakerNotificationKind::Reminder`
   (template `talk_reminder`) — parâmetro `kind` no `notify` OU rota irmã; seguir a
   convenção das rotas existentes em `routes/web.php` (grupo `public-talks/schedule`).
4. Prop `monthComplete: bool` no `Inertia::render`: verdadeiro quando **nenhuma** semana
   do mês está `open` (todas têm orador/da parceira definidos). Usar para esconder o
   botão "Convidar congregação".

### Fase B — Front (`Schedule.vue` + lang)

1. Card por semana, para todos os tipos:
   - `incoming`: "**{orador}** · {congregação de origem}" + tema (nº · título).
   - `outgoing`: "**{orador que sai}** → {congregação destino}" + tema.
   - `home`: como hoje (orador + tema), mantendo `no_speaker` quando vazio.
2. Ação de mensagem no card (não só no sheet): botão/ícone WhatsApp visível quando
   `week.notifiable`, abrindo confirmação leve — "Enviar designação" (Assignment, 1ª vez)
   ou "Pedir confirmação" (Reminder, reenvio). Mostrar o estado da resposta no card:
   enviado (`sent_at`), confirmado, remarcar pedido (`needs_reschedule` já tem badge
   destructive — complementar com quem/quando).
3. Esconder `invite-congregation` quando `props.monthComplete` (manter o `data-test`).
4. Sheet: manter edição para `home`; para `outgoing` abrir um sheet somente-leitura com
   detalhes + botão de mensagem (hoje o card nem abre porque `editable=false`).
5. Corrigir a pluralização do `pending_badge` (se o `useT` não suportar `|`, escolher a
   forma no front por `count` ou dividir em duas chaves `pending_badge_one/other`).
6. Novas chaves em `lang/pt_BR/app/public_talks.php` seção `schedule` (e espelhar nos
   demais idiomas se existirem outros locales ativos).

### Fase C — Testes (Pest, `tests/Feature/PublicTalks/`)

- Payload: semanas `incoming`/`outgoing` expõem `counterpart`, `speaker`, `notification`,
  `notifiable`; `monthComplete` verdadeiro/falso conforme semanas abertas.
- `notify` aceita `Outgoing` com orador nosso e recusa `Incoming` (404/403).
- `kind=reminder` enfileira `SendSpeakerAssignmentNotification` com `Reminder`.
- Front (`tests` de página, se padrão existir) ou assertions Inertia dos props.
- Rodar suíte dirigida: `php artisan test --compact --filter=Schedule` + testes de
  exchange que tocam a tela; `vendor/bin/pint --dirty --format agent`; `npm run build`.

## Decisões a confirmar com o dono no início do chat

1. Mensagem para semana `incoming`: fora do escopo (orador é da parceira)? Sugerido: sim.
2. "Mês fechado": basta não haver semana `open`, ou também exigir tudo `confirmed`?
   Sugerido: sem semana `open` (esconder convite ≠ mês confirmado).
3. Texto/template do lembrete (`talk_reminder`) já cobre "confirmar ou remarcar"? Se não,
   ajustar template no acervo de templates WhatsApp (ver `.ai/rules/whatsapp-templates.md`).

## Fora de escopo (o dono avisou: "depois tem mais coisa")

Não antecipar outras melhorias da tela; registrar novos pedidos como melhorias 9+.
