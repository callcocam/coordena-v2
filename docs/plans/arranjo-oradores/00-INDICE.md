# Arranjo de Oradores — Índice do plano (coordena-v2)

> Reescrita do módulo de discursos públicos do coordena (v1) neste projeto (v2).
> Cada fase tem um arquivo próprio, escrito para ser executado por um **chat separado**
> com contexto autossuficiente. **Todo chat executor deve ler este índice antes da sua fase.**
> Ordem: 0 → 1 → 2 → 3 → 4 → 5 → 6.

## Por que reescrever (lições do v1, em `~/projects/coordena/docs/plans/arranjo-oradores/`)

1. **Inbound de WhatsApp virou remendo**: N listeners disputando a mensagem, ordem sensível,
   precisou de "árbitro" tardio. → No v2: **dispatcher único** com precedência explícita desde o commit 1 (fase 4).
2. **IA como parser de resposta falhou** e foi rebaixada a fallback. → No v2: processamento
   **manual assistido** pela "mesa de trabalho" (fase 2); IA fica como evolução futura registrada.
3. **Templates nunca submetidos à Meta / scheduler nunca ativado** — muito construído sobre canal
   não validado. → No v2: submeter templates à Meta é o **primeiro item** da fase 3; ativação em
   produção é **critério de aceite** da fase 6.
4. **UX ruim no ponto crítico**: a modal de resposta da congregação convidada só deixava cadastrar
   1 oferta por vez, obrigando a reabrir/reenviar; a lista de semanas tinha informação demais.
   → No v2: mesa de trabalho com mensagem íntegra + N ofertas numa sessão (fase 2); cartões enxutos (fase 1).

## O produto (visão validada com o usuário)

- Gestão dos discursos públicos de fim de semana, **muito usada em celular/tablet → mobile first**.
- **1 time = 1 congregação local (casa)**. As demais são congregações parceiras que recebem convites.
- **Horizonte contínuo de 3 meses**: quando restam só 2 meses preenchidos, o sistema gera o mês seguinte.
- Cada mês tem 4–5 fins de semana. Slot "casa" (orador local + esboço) e permutas (entrada/saída).
- **Convite de permuta = 1 por mês do time**, cobrindo as semanas em falta. O convite **não é recriado**:
  tem **envios sucessivos** — vai a uma congregação da lista; ela preenche parte (ex.: 2 de 4 semanas);
  as restantes continuam abertas **no mesmo convite**, que segue para a próxima congregação, até o mês fechar.
- Respostas chegam como **texto livre** (WhatsApp) ou estruturadas (portal público com token).
  Preenchimento é manual, semana a semana, pela mesa de trabalho.
- Coordenador de discursos também **opera pelo WhatsApp** (menu guiado — fase 5): consultar a semana,
  disparar confirmações, ver pendências.
- Notificação ao orador local com confirmação por botão; lembretes e alertas por scheduler.

## Regras invioláveis (todas as fases)

1. **Escolha final de orador/tema é sempre humana**, com confirmação explícita. Nada é enviado
   à congregação convidada sem a ação "Confirmar e responder" (caminho único de serviço).
2. **Orador só pode ser usado se estiver cadastrado** (FK obrigatória) — sempre ligado à
   congregação dele. Na mesa de trabalho, criar orador é escopado pela congregação do envio do convite.
3. **1 saída por orador por mês**, verificada no acervo inteiro (vale entre times do mesmo dono).
4. **Texto livre de terceiros nunca é interpretado por IA nesta versão** — orador: encaminhado íntegro
   ao responsável/ajudantes; congregação parceira: aparece íntegro na mesa de trabalho.
5. **Team scope em tudo** que é do time; acervo é escopado por `owner_user_id`; catálogo de esboços é global.
6. **Mobile first**: cartões empilhados + bottom sheets são a base; desktop (duas colunas) é enhancement.
   Critério de aceite de toda tela inclui viewport mobile.
7. i18n: zero texto fixo — `lang/pt_BR/`.
8. Seguir CLAUDE.md do projeto (Boost, Pint, Pest, Wayfinder, Inertia v3 + Vue).

## Modelo de dados (fonte única — as fases referenciam daqui)

### Acervo do dono (`owner_user_id`, visível em todos os times do dono; RBAC `congregations:view|manage`)

- `congregations`: `owner_user_id` FK users, `name`, `city`, `circuit`, `address?`, `email?`,
  `contact_name?`, `contact_phone?` (normalizado), `secretary_name?`, `secretary_phone?`,
  `secretary_email?`, `meeting_weekday?`, `meeting_time?`, `exchange_opt` enum
  (`opted_in|opted_out|unknown`), timestamps, softDeletes.
- `speakers` (da casa E das parceiras): `congregation_id` FK, `name`, `role` enum
  (`elder|ministerial_servant|other`), `phone?` (normalizado), `is_active`, `notes?`,
  timestamps, softDeletes.
- `speaker_outlines`: `speaker_id`, `outline_id`, unique par — esboços que o orador prepara.

### Catálogo global

- `public_talk_outlines`: `number` unique, `title`, `theme?`, `reference_url?`, `status`,
  `replaced_by_number?`. Portado do v1 com `database/data/public_talk_outlines.php` (194 esboços).

### Nível do time

- `teams.home_congregation_id` FK nullable → congregação-casa (setup obrigatório do módulo).
- `coordinators`: `team_id`, `name`, `phone` (normalizado), `role` enum (`responsible|helper`),
  `is_active`, `user_id?`. 1 responsável ativo obrigatório; N ajudantes. Módulo bloqueado sem responsável.
- `talk_assignments`: `team_id`, `date` (fim de semana), `type` enum (`home|incoming|outgoing`),
  `speaker_id?` FK speakers (acervo — garante "orador cadastrado"), `outline_id?`,
  `counterpart_congregation_id?`, `status` enum (`open|scheduled|notified|confirmed|needs_reschedule`),
  `created_by_id?`, timestamps.
- `talk_assignment_notifications`: `talk_assignment_id`, `speaker_id`, `kind` (`assignment|reminder`),
  `wamid?` unique, `status` (`pending|sent|confirmed|declined|failed`), `sent_at`, `responded_at`,
  `response_payload` json, `sent_by_id?`.

### Permuta (fase 2)

- `exchange_invites` — **1 por time+mês**: `team_id`, `month` (date, dia 1), `status` enum
  (`open|partially_filled|filled|expired`), `created_by_id?`, timestamps. unique(`team_id`,`month`).
- `exchange_invite_sends` — cada envio do convite a uma congregação: `invite_id`,
  `congregation_id`, `channel` (`whatsapp|manual|portal`), `portal_token?` unique, `status`
  (`pending|sent|answered|declined|expired`), `sent_at?`, `answered_at?`, `sent_by_id?`.
- `exchange_messages` — histórico íntegro (alimenta a mesa de trabalho): `invite_send_id`,
  `direction` (`inbound|outbound`), `channel`, `body`, `wamid?`, `created_at`. Nunca apagado.
- `exchange_offers`: `invite_send_id`, `direction` (`incoming|outgoing`), `speaker_id` FK obrigatório,
  `target_date?` (semana; atribuível depois), `status` (`draft|selected|confirmed|discarded`),
  `source_message_id?`, `created_by_id?`.
- `exchange_offer_outlines`: `offer_id`, `outline_id` (N temas por oferta).

## Mapa de fases

| # | Arquivo | Entrega | Depende de |
|---|---------|---------|------------|
| 0 | `fase-0-fundacao.md` | Migrations, models, enums, policies, seeders portadas, serviços puros (`ScheduleHorizon`, `SpeakerAvailability`) | — |
| 1 | `fase-1-programacao.md` | Tela-mãe da programação (mobile first) + gestão do acervo + setup do módulo | 0 |
| 2 | `fase-2-permuta-e-mesa.md` | Convite mensal com envios sucessivos, mesa de trabalho da resposta, portal público, confirmação explícita | 0, 1 |
| 3 | `fase-3-whatsapp-saida.md` | Templates (submeter à Meta primeiro), jobs de envio, notificação ao orador | 2 |
| 4 | `fase-4-inbound.md` | Dispatcher único de inbound, botões do orador, texto livre → mesa/encaminhamento | 3 |
| 5 | `fase-5-conversa-coordenador.md` | Menu guiado do coordenador pelo WhatsApp | 4 |
| 6 | `fase-6-scheduler.md` | Horizonte automático, lembretes D-3/D-1, nudge/expiração, ativação em produção | 3, 4 |

## Evoluções futuras registradas (fora de escopo agora)

- Agente/IA para interpretar a resposta em texto da congregação e pré-preencher ofertas
  (sempre como **rascunho** para revisão humana na mesa de trabalho — nunca confirmação automática).
- Fluxo guiado data-por-data por chat para a congregação convidada (o v1 tentou; reavaliar só se o portal não bastar).
- Antecedências e limites configuráveis por time (fase 6 usa config global).
