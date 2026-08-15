# Melhoria 4 — Portal bidirecional: cada semana com "quem vem" e "quem vai"

> Doc autossuficiente para ser executado por um **chat separado**.
> Antes de codar: leia `00-INDICE.md` (regras invioláveis, modelo de dados), `melhoria-1-semana-e-horarios.md`
> (nota do arranjo: **semana é a chave**) e as regras em `.ai/rules/` (rode `grep -rin 'keyword' .ai/rules`).
> Terminologia: **troca(s)**, nunca "permuta". Fonte de domínio: `docs/2026-07-11-arranjo-oradores.md`.

## Nota obrigatória — regra do tema (decisão do dono, 2026-08-14)

**Quem RECEBE o orador escolhe o tema**, dentre os temas que aquele orador profere:

- **Entrada (eles → nós)**: a congregação convidada informa o orador dela + a **lista de temas
  que ele profere** (números de esboço). O tema NÃO vem decidido — o nosso coordenador escolhe
  ao confirmar (melhoria 5).
- **Saída (nós → eles)**: a convidada escolhe um orador **nosso**, a semana em que precisa dele
  e **ela escolhe o tema** da lista que mostramos (os `outlines` do nosso orador).

Outras decisões do dono na mesma conversa:

- **Semana é a unidade central do portal.** Nada de abas ou wizard: cada linha/cartão de semana
  tem os DOIS lados — enviar (orador deles) e receber (escolher um nosso).
- **Uma única submissão.** Tudo que a convidada preencher vira **proposta** na mesa do
  coordenador local; nada confirma direto (aceite/recusa/tema é a melhoria 5).
- **Dia/horário da reunião da convidada** vem do cadastro dela (`congregations.meeting_weekday`
  / `meeting_time` — a congregação parceira está cadastrada e relacionada ao convite). A
  programação é sempre por **semana**; dia/hora concreto só aparece na impressão/envio ao orador.
- **Balanço pode ficar assimétrico** (ela envia 2 e pega 3). O portal NÃO bloqueia; quem decide
  recusar para equilibrar é o coordenador local, na mesa.
- **WhatsApp livre continua valendo**: o coordenador convidado pode ignorar o portal e responder
  por texto; a mensagem cai na mesa e o coordenador local programa manualmente (fluxo já existente
  via `PartnerReplyHandler` → mesa; não regredir).

## Problema atual

`resources/js/pages/publicTalks/ExchangePortal.vue` + `ExchangePortalController` cobrem só UMA
direção: a convidada cadastra oradores dela (nome, fone, **um** `outline_number`, semana) para as
NOSSAS semanas abertas. A lista dos nossos oradores ("Nossos oradores disponíveis para retribuir")
é texto morto — não dá para escolher um orador nosso, nem semana, nem tema. E a oferta deles com
um único esboço contradiz a regra do tema acima.

## Objetivo

Portal (rota pública por `portal_token` do `ExchangeInviteSend`) organizado por semana, em duas
listas de semanas:

1. **Nossas semanas abertas** ("Semanas em que PRECISAMOS de orador") — para cada semana, a
   convidada pode indicar **um orador dela**: nome, telefone (opcional), e a **lista de números
   de esboço que ele profere** (múltiplos, tags/chips). Sem escolha de tema.
2. **Semanas dela** ("Semanas em que VOCÊS querem receber um orador nosso") — a convidada
   adiciona semanas do mês da troca (gerar as semanas do mês do convite; sem dia/hora) e, para
   cada uma, escolhe **um orador nosso** (dropdown com os nossos oradores expostos —
   `SpeakerAvailability::availableFor`) e **um tema** dentre os `outlines` daquele orador
   (dropdown dependente do orador escolhido).

Uma única ação **"Enviar resposta"** submete tudo junto.

## Fases

### Fase A — Backend: submissão bidirecional

- `SubmitExchangePortalRequest`: passar a aceitar payload com dois arrays:
  - `incoming[]`: `{week (date da NOSSA semana aberta), speaker_name, phone?, outline_numbers[]}`
  - `outgoing[]`: `{week (semana do mês, validada dentro do mês do convite), speaker_id (nosso,
    precisa estar entre os expostos), outline_id (precisa pertencer a `speaker->outlines`)}`
- `ExchangePortalController::submit`:
  - `incoming` → `Speaker` `firstOrCreate` na congregação parceira + `ExchangeOffer`
    `direction=incoming`, `target_date=week`, `status=Draft`, e **anexar todos** os esboços em
    `exchange_offer_outlines` (resolver número→`PublicTalkOutline`; número desconhecido: criar
    outline provisório se o padrão atual já fizer isso, senão ignorar com aviso — checar
    convenção existente antes).
  - `outgoing` → `ExchangeOffer` `direction=outgoing`, `speaker_id` nosso, `target_date=week`,
    **um único** outline no pivot (o tema que ELA escolheu), `status=Draft`.
  - Marcar o send como respondido pelo portal (comportamento atual — `portal_submitted`/status,
    conferir no código) e **notificar o coordenador local** (mesmo aviso já disparado hoje).
- Nada de confirmação automática: ofertas ficam `Draft` aguardando a mesa (melhoria 5).

### Fase B — Frontend: página por semana

- Reescrever `ExchangePortal.vue` mantendo o layout/branding atual:
  - Cabeçalho: nome das congregações + mês + explicação curta em duas frases do que fazer
    (uma por direção).
  - Seção 1 (nossas semanas abertas): um cartão por semana (`openWeeks` que o controller já
    manda), com campos nome/telefone e um input de esboços múltiplos (chips de números).
    Semana sem orador = simplesmente não preencher (não obrigar).
  - Seção 2 (semanas deles): botão "Adicionar semana" → linha com select de semana do mês
    (backend manda as semanas do mês do convite), select de orador nosso (props já têm
    `homeSpeakers`; incluir `id` + `outlines` [número + título]) e select de tema filtrado
    pelo orador.
  - Remover a lista de texto "Nossos oradores disponíveis para retribuir" — ela vira o
    dropdown funcional da seção 2 (a informação continua visível, agora acionável).
- Validação client-side leve; erros de validação do servidor exibidos por linha (`InputError`).

### Fase C — Ajustes de contorno

- Controller `show`: incluir nas props as semanas do mês do convite (para a seção 2) e os
  `outlines` de cada `homeSpeaker`.
- Template WhatsApp do convite (`coordena_exchange_invite`) e texto do portal: revisar apenas o
  wording se citar "esboço único"; **não** mexer na estrutura de templates sem necessidade
  (regras em `.ai/rules/whatsapp-templates.md`).

## Testes (Pest, obrigatórios)

- Feature `ExchangePortalTest` (ou o existente `ExchangePagesTest`/similar — reusar):
  - submissão só `incoming` com múltiplos esboços → oferta Draft com N outlines no pivot;
  - submissão só `outgoing` → oferta Draft outgoing com 1 outline escolhido por ELA;
  - submissão mista (2 incoming + 3 outgoing) → 5 ofertas Draft, send marcado como respondido;
  - `outgoing` com `outline_id` que o orador não profere → erro de validação;
  - `outgoing` com `speaker_id` não exposto → erro de validação;
  - semana fora do mês do convite → erro de validação;
  - token fechado/expirado continua bloqueando (comportamento atual).
- Rodar a suite de PublicTalks afetada + `vendor/bin/pint --dirty --format agent`.

## Fora de escopo (melhoria 5)

Mesa de montagem: escolher tema dos incoming, aceitar/recusar por semana, equilibrar balanço,
confirmar → designações + notificações. **Não** implementar aqui.
