# Arranjo de Oradores (Discursos Públicos)

**Data:** 2026-07-11
**Status:** plano (não iniciado)
**Contexto de pesquisa:** deep-research verificado (25/25 claims) — ver memória `arranjo-oradores-dominio` e `arranjo-oradores-visao-produto`.

## 1. Objetivo

Ajudar o **coordenador dos discursos públicos** de uma congregação a manter, **sozinho e sem depender de que a outra congregação use o app**, uma **programação rolante de 3 meses** do discurso público da reunião de fim de semana — arranjando oradores em três modos: **local** (orador da própria congregação discursa aqui), **que chega** (orador visitante vem discursar aqui) e **que sai** (orador nosso vai discursar em outra). A permuta com outras congregações é negociada **por WhatsApp**.

## 2. Princípios (decididos com o usuário)

1. **Independência total por congregação.** Nada de sync entre congregações (a crítica aos outros softwares). Cada coordenador mantém sua própria base. A congregação parceira pode **nem usar o Coordena** — só troca mensagens de WhatsApp.
2. **Uma única programação viva.** Não são programas avulsos; é um cronograma contínuo que se atualiza. Oradores entram/saem; esboços podem ser desabilitados temporariamente.
3. **Horizonte rolante de 3 meses.** Todo início de mês, programa-se o 3º mês à frente (buffer sempre cheio).
4. **Regras são sugestão, não trava.** Não repetir esboço cedo demais e equilibrar rodízio de permuta são **recomendações visuais** (recência), não bloqueios — quem decide é o coordenador (confirmado na pesquisa).
5. **Catálogo padrão semeado.** Os ~194 esboços vêm prontos (número+título) como referência global read-only; cada congregação habilita/desabilita e marca quais oradores dão cada um.

## 3. Modelo de dados

Convenções do Coordena: ULID (`HasUlids`), escopo por time (`BelongsToTeam`), `#[Fillable([...])]` por atributo, enums string-backed em `app/Enums`, migrations com cabeçalho-comentário.

### 3.1 Catálogo (global, NÃO escopado por time)
- **`public_talk_outlines`** — referência semeada, read-only para o usuário.
  - `id` (ulid), `number` (int, unique), `title`, `theme` (nullable), `status` (enum `active`/`discontinued`), `replaced_by_number` (int nullable), `revised_at`/dates (nullable), timestamps.
  - Seeder com os 194 (número+título). Modela versão/descontinuação (ex.: Nº 82→129). O **título é derivado do número** ao agendar (KHS auto-preenche).

### 3.2 Base do coordenador (escopado por time)
- **`partner_congregations`** — congregações parceiras (EXTERNAS; a contraparte pode não usar o app).
  - `id` (ulid), `team_id`, `name`, `city`/`circuit` (nullable), `coordinator_name`, `coordinator_phone` (E.164, normalizado como em `Volunteer::normalizePhone`), **`meeting_weekday`** (tinyint 0–6, convenção Carbon 0=domingo, nullable), **`meeting_time`** (time, nullable), `is_active`, `rotation_order`/`last_invited_at` (cursor do round-robin), `notes`, timestamps, softDeletes.
  - **Dia e horário da reunião** importam: quando nosso orador vai para lá (outgoing) ou quando o deles vem (incoming), precisamos saber o dia/horário do fim de semana da parceira. A **congregação-casa** (o time) também tem seu dia/horário — armazenado em settings do time (ver §3.5, usado na geração de slots na Fase 1).
  - *Decisão em aberto:* reusar o `Congregation` existente (já tem `contact_*`) vs. entidade nova. **Recomendo entidade nova** — `Congregation` hoje é "congregação que serve eventos / de voluntários", semântica diferente. (Ver §9.)
- **`speakers`** — oradores (locais e das parceiras num só lugar).
  - `id` (ulid), `team_id`, `partner_congregation_id` (nullable; **null = orador local/da casa**), `name`, `phone` (nullable), `can_travel` (bool — aprovado p/ discursar fora), `is_active`, `notes`, timestamps, softDeletes.
- **`speaker_outline`** — pivô N:N (quais esboços cada orador profere).
  - `speaker_id`, `outline_number`. UX simétrica: orador→filtra esboços / esboço→filtra oradores.
- **`team_outline_settings`** — habilita/desabilita esboço por congregação (o "desabilitado por um tempo").
  - `team_id`, `outline_number`, `is_enabled` (default true). Ausência = habilitado.

### 3.5 Settings da congregação-casa (Fase 1)
- Dia/horário da reunião de fim de semana da própria congregação (`home_meeting_weekday`, `home_meeting_time`) — usado para gerar as datas dos slots da programação rolante. Armazenar em uma tabela de settings do time (ou colunas no time). Definido na Fase 1, quando a geração de slots entra.

### 3.3 A programação (Fase 1 — IMPLEMENTADO)
- **`talk_assignments`** — o coração da programação.
  - `id` (ulid), `team_id`, `congregation_id` (a **casa**), `date` (o fim de semana),
    **`kind`** (enum `home`/`outgoing` — o que a linha É), `speaker_id` (nullable enquanto aberto),
    `outline_number` (nullable), `partner_congregation_id` (origem se o orador vem de fora, destino se o nosso sai),
    `status` (enum `open`/`pending`/`confirmed`/`cancelled`), `notes`,
    campos-só-de-incoming (Fase 3): `chairman_id`, `watchtower_reader_id`, `hospitality_note`,
    `exchange_invite_id` (nullable — de qual convite veio), timestamps.
  - **`direction` é DERIVADO, não armazenado:** `outgoing` se `kind=outgoing`; senão `incoming` se tem `partner_congregation_id`, senão `local`. Evita o estado impossível "slot aberto com direction já definida" — quando o slot nasce não se sabe ainda quem vai discursar.
  - A "programação do fim de semana" = as linhas `home` (1 por data). `outgoing` é a trilha paralela (nossos oradores viajando), que pode ter **mais de uma por data**.
  - Por isso **não há unique composto** no banco: ele protegeria o slot da casa mas travaria múltiplos `outgoing` no mesmo fim de semana. A unicidade do slot da casa é garantida na aplicação (`ScheduleHorizon` usa `firstOrCreate` em transação) + índice `(congregation_id, date)`.

### 3.6 Capacidade de permuta — o que determina o TOM/NATUREZA da mensagem (refinamento do usuário, 11/07)
O usuário levantou (antes de qualquer mensagem existir, para não retrabalhar): **nem todo pedido é uma permuta**, e o texto tem que dizer a verdade.
- **Congregação pequena:** "tenho 3 oradores e os 3 já foram designados, mas falta preencher um dia" → o que se manda **não é uma troca**, é um **pedido/convite** ("vocês poderiam nos ceder um orador?"). Se a mensagem falar em permuta, mente.
- **Primeiro contato** com a congregação → a mensagem precisa de tom de **apresentação** ("sou o coordenador de discursos da congregação X..."); nos contatos seguintes, direto ao ponto.

Modelagem que sustenta isso (Fase 1, para a Fase 2 só ler o estado):
- **`congregations.max_outgoing_per_month`** (nullable; null = sem limite) — quantos oradores a congregação consegue mandar para fora por mês. É o cadastro do "sou pequena, 1 por mês".
- **Regra de domínio:** um orador não sai mais de **1 vez por mês** (não sobrecarregar o mesmo irmão).
- **`ExchangePlanner`** deriva, para (mês, parceira):
  - **`kind`** (enum `ExchangeRequestKind`): `exchange` (tenho orador para oferecer → é permuta de verdade), `request` (não tenho → peço sem contrapartida), `offer` (só ofereço).
  - **`isFirstContact`**: não existe nenhum `talk_assignment` passado com aquela congregação → tom de apresentação.
  - **`availableSpeakers`**: ativos + `can_travel` + sem `outgoing` naquele mês, respeitando `max_outgoing_per_month` da casa.
- A Fase 2 escolhe o **template** por `kind` + `isFirstContact` (matriz de templates, não texto improvisado).

### 3.4 Máquina de estados da permuta (WhatsApp)
- **`exchange_invites`** — um convite a uma congregação parceira para um mês.
  - `id` (ulid), `team_id`, `partner_congregation_id`, `direction` (Fase 2 = `incoming`: pedimos orador pra cá), `month` (date, 1º dia),
    `status` (enum `draft`/`sent`/`awaiting`/`partially_accepted`/`closed`/`declined`/`expired`),
    `token` (Str::random(48)), `wamid` (última msg enviada, p/ correlação), `window_expires_at` (janela 24h da Meta),
    `sent_at`/`responded_at`/`closed_at`, `raw_last_payload` (json), timestamps.
- **`exchange_invite_weeks`** — a negociação semana a semana dentro do convite.
  - `id`, `exchange_invite_id`, `date` (o fim de semana ofertado), `status` (`offered`/`accepted`/`declined`/`filled`),
    `speaker_name` (texto livre quando é orador novo da parceira), `speaker_id` (nullable, se já cadastrado), `outline_number` (nullable).
  - Ao fechar (`filled`), gera/atualiza o `talk_assignment` `incoming` correspondente.

## 4. Motor de programação rolante (round-robin)

Job agendado (scheduler) no **início de cada mês**: garante que o **3º mês à frente** existe como conjunto de slots `open` (um por fim de semana). Depois, para preencher um mês:

1. Lista os fins de semana `open` do mês-alvo.
2. Pega a **próxima congregação parceira** pela ordem de rodízio (`rotation_order`/`last_invited_at`, e o histórico de permuta desempata — quem não visitamos há mais tempo primeiro).
3. Abre um `exchange_invite` com as semanas abertas e **envia o convite** (§5).
4. Conforme a parceira aceita semanas, os slots viram `pending`→`confirmed` (`talk_assignments incoming`).
5. **Semanas que sobraram** → próxima congregação da lista. Repete até o mês fechar ou a lista esgotar (então alerta o coordenador p/ preencher local).
6. Sugere o esboço por **recência** (nunca dado / há mais tempo primeiro), respeitando `speaker_outline` e `team_outline_settings` — **sugestão, não trava**.

O coordenador também preenche **manualmente** qualquer slot (orador **local**, ou incoming já combinado por fora). O motor nunca sobrescreve slot confirmado.

## 5. Fluxo de convite por WhatsApp (o "nó") — híbrido botões + IA

**Restrição-chave (infra real):** fora da janela de 24h a Meta só aceita **template aprovado**; listas/texto/IA só valem **dentro** da janela que um toque de botão abre. Então:

1. **Iniciar (business-initiated) → TEMPLATE.** `coordena_exchange_invite`: corpo com mês + nº de semanas abertas + botões quick-reply `[Quero participar]` / `[Agora não]`. O toque **abre a janela de 24h** e volta pelo webhook (`type: button`/`button_reply`, já tratado em `AssignmentResponseHandler::buttonLabel`).
2. **Dentro da janela (sessão):**
   - Enviar as **semanas abertas**. Opção A: `sendInteractive` (list message da Meta — hoje existe no pacote mas **não é usado**; precisa passar a parsear `interactive.list_reply.id = opt_N`). Opção B (recomendada p/ multi-semana, que a lista single-select complica): **texto** enumerando as semanas + o coordenador responde **livre** ("dia 11 e 25, irmão João, temas 12 e 88").
   - **IA (Claude) interpreta** o texto livre → `{semanas, orador, esboços}`, casa com `exchange_invite_weeks`, pede confirmação.
   - **Confirmação final** por botão (quick-reply de sessão / interactive button) → marca `filled` e gera os `talk_assignments`.
3. **Janela fechou sem terminar** → reengata com o template (nova business-initiated) retomando as semanas ainda `offered`.
4. **Correlação do inbound:** por `context.id == wamid` (senão telefone→convite `awaiting` mais recente), idêntico ao padrão de `AssignmentResponseHandler::resolveNotification`. **Novo listener** em `WhatsAppMessageReceived` (hoje só há `HandleAssignmentResponse`; cada listener filtra o que é seu — este casa telefone do remetente com `partner_congregations.coordinator_phone` que tenha convite ativo).

### 5.1 O Coordena é BROKER entre os DOIS coordenadores (refinamento do usuário)
O coordenador da CASA também opera **por WhatsApp**, não só pelo app — o Coordena fica no meio, relaia e mantém o app em sincronia. Fluxo típico (ex.: escolher o orador nosso e o tema que vai sair, ou confirmar quem cobre um slot):
1. O coordenador da **parceira responde** (aceita semanas / pede orador).
2. O Coordena **avisa o coordenador da casa** (WhatsApp) que há algo a decidir.
3. O coordenador da casa **responde por mensagem**: escolhe **orador + tema**. Como o orador/tema da casa vêm do nosso próprio cadastro (lista finita), aqui cabe **list message nativa** (lista de oradores ativos → depois esboços que ele dá) — estruturado, sem depender de IA. (A IA entra no lado da parceira, quando o orador/tema dela é texto novo.)
4. O Coordena **atualiza a programação no app** E **relaia a decisão de volta** ao coordenador da parceira (confirmação).

Consequências de modelagem:
- Precisamos do **WhatsApp do coordenador da casa** (telefone do `User`/perfil) além do da parceira.
- **App e WhatsApp são duas interfaces do mesmo estado** — o coordenador da casa pode agir por qualquer um; ambos atualizam `exchange_invites`/`talk_assignments`. O estado (não a UI) é a fonte da verdade.
- A máquina de estados tem **dois atores humanos alcançáveis por WhatsApp** (casa + parceira); cada mensagem recebida é roteada pelo telefone do remetente ao papel certo (casa vs parceira) dentro do convite ativo.
- Toda gravação vinda de texto livre passa por **confirmação** antes de efetivar (nunca gravar direto do parse).

### Lacunas a construir (levantadas no mapa da infra)
- **Sender de botão interativo de sessão** (ou reusar `CloudApiClient::sendInteractive` p/ list) — hoje botão nativo só via quick-reply de template.
- **Parse de `list_reply`** em `AssignmentResponseHandler`/novo handler (id `opt_N`).
- **Novo agregado de conversa** (`exchange_invites`/`_weeks`) — a state machine por webhook foi removida em 07/07; construir do zero combinando o padrão token/status/enum de `VolunteerDataRequest` + `wamid`/telefone de `AssignmentNotification`.
- **Serviço de parsing por IA** (Claude) do texto livre → estrutura (reusa a infra de `ai`/`aiState` já compartilhada nas props).

## 6. Templates da Meta — **5 escritos, NENHUM submetido** ⏳

Os cinco existem em `whatsapp-templates/` e estão mapeados em `config/whatsapp-cloud.php`.
**Ficaram parados de propósito:** o usuário está montando um **sandbox no pacote
`callcocam/laravel-whatsapp-cloud`** para exercitar o fluxo inteiro sem gastar
submissão nem reputação do número. Só depois disso rodar
`php artisan whatsapp:template:create` para cada um.

| chave (config) | arquivo | quando sai |
|---|---|---|
| `public_talks.intro` | `coordena_intro` | **A porta.** Apresentação + salvar contato + opt-in. Botões *Quero participar* / *Não quero*. Nenhum convite sai antes do aceite. |
| `public_talks.exchange_invite` | `coordena_exchange_invite` | Convite quando **temos orador para retribuir** (é troca de verdade). |
| `public_talks.talk_request` | `coordena_talk_request` | Convite quando **não temos**: diz com todas as letras que *não seria uma troca, é um pedido de ajuda*. |
| `public_talks.exchange_reply` | `coordena_exchange_reply` | Avisa o coordenador da CASA que a congregação respondeu (≤ 1×/hora). |
| `public_talks.exchange_confirmation` | `coordena_exchange_confirmation` | Confirma à congregação o que entrou na grade. |

**O que o sandbox precisa exercitar** (é disto que estes templates dependem, e é
onde a Meta/o pacote costumam surpreender):
1. **Quick reply de template** → o inbound volta como `type: button` com
   `button.text` **e** `context.id` = o wamid da mensagem tocada. A correlação
   inteira depende desse `context.id`: *Quero participar* é rótulo de **dois**
   botões (apresentação e convite), e só o wamid os separa.
2. **Texto de sessão** (`sendSessionText`) dentro da janela de 24h que o toque abre.
3. **Texto livre inbound** (`type: text`) → é o que o `ExchangeReplyParser` lê.
4. Guard-rails do `TemplateBuilder` já cobertos por
   `tests/Feature/Whatsapp/TemplateDefinitionsTest.php` (corpo não pode terminar
   em variável — code 100 da Meta; nº de exemplos = nº de variáveis).

Página de revisão dos textos (bolhas de WhatsApp, com as respostas automáticas):
https://claude.ai/code/artifact/358a0e42-2829-4aa8-a117-b3df5348915e

*(Descartados no caminho: `coordena_exchange_invite_intro` e
`coordena_talk_request_intro` — a apresentação virou mensagem própria, então o
convite não precisa mais de variante com tom de apresentação; e
`coordena_exchange_reengage`, que só faria sentido se a janela de 24h fosse o
gargalo, o que não é: quem responde por botão já abre a janela.)*

## 7. Papéis / permissões
- Nova permissão fina de domínio (padrão RBAC db-driven já existente): `public-talks:manage`. O coordenador dos discursos públicos recebe. Sem papel novo obrigatório — reusar o funil de permissões por time.

## 7.2 Coordenador como cadastro próprio (refinamento 11/07 — segundo)
Decisão do usuário: **o coordenador é uma entidade separada, com histórico**. Modelo:
- **`coordinators`** (novo): `congregation_id` (a congregação que coordena), `name`, `phone`, `user_id` (nullable — preenchido quando o coordenador é usuário do time), `is_active` (**apenas 1 ativo por congregação**; os demais viram histórico), timestamps.
- **A congregação participa da permuta ao TER um coordenador** (sem flag "participa").
- **Dia/horário da reunião ficam NA congregação** (`meeting_weekday`, `meeting_time`), não no coordenador.
- **Contato vs usuário:** coordenador de outras congregações = contato (nome+WhatsApp). Coordenador da **minha** congregação (`users.congregation_id`) = **usuário**. No cadastro, se for da minha congregação: botão **"Convidar como usuário"** (reusa `TeamInvitationController`) quando ainda não tem conta; se **já for usuário**, seleciona e envia um aviso "você agora é o coordenador de discursos". O botão só aparece na congregação que o usuário logado pertence.
- **A DECIDIR (base da congregação):** reusar o `Congregation` existente (aposenta `partner_congregations`, move meeting/rotation pra ele, oradores passam a referenciar `congregation_id`) OU manter `partner_congregations`. Recomendo reusar `Congregation` — casa = `users.congregation_id`, parceiras = outras congregações com coordenador; deixa o fluxo "minha congregação" coerente (mesma entidade).

## 7.1 Hub + coordenador ↔ congregação (refinamento do usuário, 11/07)
A feature é **baseada em configuração**, então tem um **hub/dashboard** (`public-talks.index`, feito) como porta de entrada: no centro a **programação/convites** e a **lista de arranjos**; ao redor, **cards de atalho** pros cadastros (congregações parceiras, oradores, esboços). O nav aponta pro hub, não pros cadastros soltos.

**Coordenador ↔ congregação existente:** em vez de "casa = time" abstrato, o usuário quer **cadastrar o coordenador de discursos e relacioná-lo ao `Congregation` que já existe** (a congregação-casa é um registro real de `Congregation`, team-scoped, que já usamos para voluntários/eventos). Implicações a decidir (§9, itens 1–2 revisados): a programação/arranjos passam a ser por **congregação-casa** (cada `Congregation` tem 1 coordenador de discursos e sua própria grade), e o coordenador é provavelmente um `User` (reusa `users.congregation_id` de [[user-congregation-relacao]]) ou um contato (nome+WhatsApp). Confirmar antes de modelar.

## 8. Telas (Inertia/Vue)
- **Programação (3 meses):** grade por fim de semana com modo (local/chega/sai), orador, nº+título do esboço, status; realce de recência do esboço; ações de preencher/convidar.
- **Oradores:** lista local + por parceira; edição de quais esboços cada um dá (picker simétrico).
- **Congregações parceiras:** cadastro (nome, coordenador, WhatsApp, ordem de rodízio).
- **Catálogo de esboços:** os 194, com toggle habilitar/desabilitar por congregação.
- **Convites de permuta:** acompanhamento do estado das conversas de WhatsApp (enviado/aguardando/parcial/fechado), com timeline por semana.
- **Relatórios (Fase 3):** histórico de permuta por congregação (equilíbrio do rodízio), oradores que chegam/saem.

## 9. Decisões em aberto
1. **Congregação parceira:** entidade nova (recomendado) vs. reusar `Congregation`. Conecta à tensão de `user-congregation-relacao` (congregação escopada por time).
2. **"Casa" = time** ou um `Congregation` específico? Assumido: a programação pertence ao **time** (workspace do coordenador). Confirmar se um time pode coordenar mais de uma congregação-casa.
3. **Multi-semana no WhatsApp:** list message single-select (várias mensagens) vs. texto-livre+IA (recomendado). 
4. **Reciprocidade/outgoing:** Fase 1 foca em **incoming** (encher nossa programação). Oferecer nossos oradores (outgoing) na mesma conversa = Fase 3.

## 10. Fases de entrega
- **Fase 0 — Base:** catálogo semeado (194) + `partner_congregations` + `speakers` + `speaker_outline` + `team_outline_settings` + telas de cadastro. Sem WhatsApp. *(Entrega valor sozinha: cadastro + catálogo.)*
- **Fase 1 — Programação manual rolante:** `talk_assignments`, grade de 3 meses, preenchimento manual (local/incoming/outgoing), sugestão de esboço por recência, job de manutenção do horizonte. *(Já é um planner utilizável.)*
- **Fase 2 — Permuta por WhatsApp:** `exchange_invites`/`_weeks`, templates, sender interativo, novo listener + parse (`list_reply`/IA), motor round-robin. *(O coração.)*
- **Fase 3 — Reciprocidade e extras:** outgoing/reciprocidade na mesma conversa, presidente/leitor/hospitalidade, relatórios de histórico de permuta, lembretes.

## 11. Riscos
- Aprovação de templates pela Meta (histórico de rejeição code 100) e janela de 24h — desenhar cópias no estilo aprovado.
- Ambiguidade do parsing por IA — sempre confirmar com o coordenador antes de gravar; nunca gravar direto do texto livre.
- Anti-ban (jitter/variação) já tratado na infra — reaproveitar ao disparar convites em lote no round-robin.
