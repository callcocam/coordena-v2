# Melhoria 3 — Convite de troca conversacional (aceite antes de oradores) e economia de Meta

> Doc autossuficiente para ser executado por um **chat separado**.
> Antes de codar: leia `00-INDICE.md`, `melhoria-1-semana-e-horarios.md`,
> `melhoria-2-apresentacao-e-optin.md` (se já executadas), as regras em `.ai/rules/` — em
> especial `inbound.md` (**todo inbound passa pelo `InboundDispatcher::HANDLERS`; nunca criar
> listener paralelo**; botões roteiam por correlação de `wamid`, nunca pelo rótulo) — e,
> **obrigatoriamente**, a pesquisa `docs/2026-07-11-arranjo-oradores.md`: ela é o padrão de
> consulta sobre como funciona o arranjo de trocas de oradores das Testemunhas de Jeová.
> Terminologia: **troca(s)**, nunca "permuta".

## Por que essa melhoria existe

1. **Custo Meta.** Hoje o convite (`coordena_exchange_invite`) já despeja o link do portal no
   template. Cada template business-initiated **abre uma conversa cobrada** na Meta. A resposta
   do convidado abre a **janela de sessão de 24h**, onde mensagens são gratuitas. Logo, a
   estratégia é: **1 template curtíssimo por convite** (abridor), e TODO o conteúdo rico
   (semanas, oradores, link) só vai como **mensagem de sessão depois que o convidado responde**.
   Nunca enviar um segundo template quando a janela está aberta (`hasOpenWindow` em
   `App\Jobs\SendExchangeInvite` já sabe checar isso — reutilizar).
2. **Não expor oradores antes do aceite.** O convidado primeiro diz **sim ou não** para a troca
   do mês. Só depois do "sim" liberamos a lista de oradores — os nossos disponíveis e a coleta
   dos deles. Antes do aceite, o convite informa apenas **quantos** oradores temos para trocar,
   nunca quem.
3. **Nem sempre é troca.** Se não temos orador disponível no mês, a mensagem **não pode ser de
   troca** — é um **pedido de ajuda para completar o arranjo**.

## Regras de domínio (validar contra `docs/2026-07-11-arranjo-oradores.md`)

- **1 fora por orador/mês**: cada orador faz no máximo **um discurso fora** por mês
  (`App\Services\PublicTalks\SpeakerAvailability::canGoOut` / `availableFor` já implementam;
  `MAX_OUTGOING_PER_SPEAKER_PER_MONTH` — não duplicar a regra, consumir o serviço).
- **1 local por mês** é convenção, **não trava**: um orador local discursa na própria
  congregação. O coordenador pode decidir **não usar orador local nenhum** e preencher o mês só
  com oradores de fora. Nada no código deve bloquear isso (regras são sugestão — princípio 4 do
  doc de pesquisa).
- **Só apresentar oradores livres**: em qualquer lista/oferta, apenas oradores **sem discurso de
  fora marcado naquele mês** (via `SpeakerAvailability::availableFor`).
- Exemplo de escassez que o fluxo precisa cobrir: congregação com 2 oradores → no máximo 2
  saídas/mês; um mês de 4–5 semanas **não fecha só com troca**.

## Estado atual (reutilizar, não duplicar)

- `database/whatsapp-templates/coordena_exchange_invite.php` — abridor atual sem botões, mas
  **já com o link do portal no corpo** (isso muda: ver abaixo).
- `App\Jobs\SendExchangeInvite` — decide template vs. sessão (`hasOpenWindow`), monta params.
- `App\Services\PublicTalks\SpeakerAvailability` — disponibilidade mensal do orador.
- `App\Services\PublicTalks\ExchangeRoundRobin` + `ExchangeInviteManager` — rodízio e convite
  mensal com envios sucessivos (o convite do mês é UM só, reenviado para a próxima congregação
  da lista conforme sobram semanas).
- `InboundDispatcher::HANDLERS` — ponto único de inbound; `PartnerReplyHandler` trata resposta
  da parceira; correlação por `wamid`.
- M2 (se executada): opt-in/apresentação — o convite de troca **só vai para `opted_in`**.

## Objetivo

### 1. Novo template abridor `coordena_exchange_invite` (v2) — curto, com botões, SEM link

Corpo (aprovar com o usuário antes de submeter à Meta; **sandbox primeiro; nada é submetido ou
enviado sem aprovação explícita**):

> Olá, *{{contato}}*! Aqui é da congregação *{{nossa}}*. 🙏
> Estamos montando a programação de discursos públicos de *{{mês}}* e temos *{{N}} oradores*
> disponíveis para uma troca com vocês. Podemos combinar?

Botões: **"Sim, vamos combinar"** / **"Este mês não"**.

- `{{N}}` = `SpeakerAvailability::availableFor(casa, mês)->count()`. **Sem nomes.**
- **Sem link do portal no template.** O link passa a ir na mensagem de sessão pós-aceite
  (grátis). Isso também encurta o template e reduz risco de rejeição da Meta.
- A Meta rejeita `\n` dentro de variável e corpo terminando em variável — manter fecho fixo.

### 2. Variante "pedido de ajuda" `coordena_exchange_help` (novo template)

Quando `availableFor(casa, mês)` está **vazio** (ou abaixo do necessário — ver decisão em
aberto), o envio para o rodízio usa este template em vez do de troca:

> Olá, *{{contato}}*! Aqui é da congregação *{{nossa}}*. 🙏
> Estamos completando a programação de *{{mês}}* e neste mês não temos oradores para oferecer
> em troca. Vocês poderiam nos ajudar enviando um orador?

Botões: **"Podemos ajudar"** / **"Este mês não"**.

- Na sessão pós-aceite, incluir a sugestão de **contato direto com os oradores**: se o
  coordenador convidado preferir, pode falar direto com os oradores dele e, se eles toparem ir,
  vira um **arranjo entre eles** — o sistema só registra o resultado (semana + orador).

### 3. Máquina de estados do convite-conversa

Desenhe antes de codar (papel/markdown) e valide contra os cenários abaixo. Estender
`ExchangeInviteSendStatus`/modelo de envio com o estágio da conversa, algo como:
`sent → accepted | declined | expired`, e só **após `accepted`**:

- Mensagem de sessão com: semanas em aberto do mês (M1: âncora na segunda-feira + dia/horário
  da reunião), **lista dos nossos oradores disponíveis** (nome + esboços, só os livres no mês) e
  o **link do portal** (`portal_token`) para registrar as ofertas deles.
- `declined` ("Este mês não") → agradecer, marcar o envio como recusado e **passar o mesmo
  convite para a próxima congregação do rodízio** (comportamento de envios sucessivos já
  existente — não recriar o convite).
- Texto livre em vez de botão → mantém o fluxo atual do `PartnerReplyHandler` (mesa de
  trabalho); **nunca interpretar texto livre por IA** (regra inviolável do índice).

### 4. Gate de exposição de oradores

- Portal e mensagens **não mostram nomes de oradores** enquanto o envio não estiver `accepted`.
- Toda listagem de oradores (sessão, portal, mesa) filtra por
  `SpeakerAvailability::availableFor` do mês do convite.

### 5. Novos handlers de botão

`ExchangeInviteButtonHandler` (sim/não do convite e da variante ajuda) inserido na posição
correta de `InboundDispatcher::HANDLERS` (provavelmente antes de `PartnerReplyHandler`;
justifique a posição e atualize o teste de contrato `InboundDispatcherTest`). Correlação por
`wamid` do template enviado, nunca pelo rótulo do botão.

## Cenários que os testes precisam cobrir (Pest, feature)

1. Casa com 3 oradores livres → convite de troca com `{{N}} = 3`; nomes ausentes do payload.
2. Convidado clica "Sim" → sessão com semanas + oradores + link; envio `accepted`.
3. Convidado clica "Este mês não" → envio `declined`; próxima congregação do rodízio recebe o
   MESMO convite do mês.
4. Casa sem orador livre (2 oradores, ambos já com fora no mês) → template de **ajuda**, não de
   troca; sessão pós-aceite sugere contato direto com os oradores.
5. Janela de 24h aberta → convite vai por **sessão**, nunca segundo template.
6. Orador com discurso fora no mês nunca aparece em lista/oferta.
7. Texto livre durante convite pendente → cai na mesa (`PartnerReplyHandler`), sem mudança de
   estado automática.

## Decisões em aberto (perguntar ao usuário antes de implementar)

1. Limiar da variante ajuda: só quando `N = 0`, ou também quando `N <` semanas em aberto?
2. O "não" do convite deve alimentar recência do rodízio (ex.: pular a congregação por X meses)?
3. A contagem `{{N}}` deve descontar oradores já oferecidos em convites vivos de outros meses?

## Fora de escopo

- Interpretação de texto livre por IA (evolução futura registrada no índice).
- Alterar o mecanismo do portal (`portal_token`) além de esconder oradores pré-aceite.
- Submeter templates à Meta sem aprovação explícita do usuário (sandbox primeiro, sempre).
