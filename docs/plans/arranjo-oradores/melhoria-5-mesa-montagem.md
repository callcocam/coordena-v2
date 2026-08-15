# Melhoria 5 — Mesa de montagem: eu escolho o tema, aceito/recuso por semana e confirmo a troca

> Doc autossuficiente para ser executado por um **chat separado**, DEPOIS da melhoria 4.
> Antes de codar: leia `00-INDICE.md`, `melhoria-1-semana-e-horarios.md` (semana é a chave),
> `melhoria-4-portal-bidirecional.md` (regra do tema + decisões do dono) e `.ai/rules/`
> (rode `grep -rin 'keyword' .ai/rules`). Terminologia: **troca(s)**, nunca "permuta".

## Contexto — o que chega até aqui

Depois da melhoria 4, uma resposta da congregação convidada (portal OU WhatsApp livre) produz:

- `ExchangeOffer direction=incoming status=Draft` — orador DELES numa semana NOSSA, com a
  **lista de temas que ele profere** no pivot `exchange_offer_outlines` (tema ainda não decidido).
- `ExchangeOffer direction=outgoing status=Draft` — orador NOSSO numa semana DELES, com **um**
  tema já escolhido por ELES (quem recebe escolhe o tema).
- Resposta por texto livre: `ExchangeMessage` na mesa, sem ofertas — o coordenador lê e cria as
  ofertas manualmente.

## Objetivo (decisões do dono, 2026-08-14)

Uma tela de **montagem da troca** por convite/send onde o coordenador local:

1. **Vê o pacote inteiro** proposto pela convidada, agrupado por semana e direção, com o balanço
   visível (ex.: "eles enviam 2 · levam 3").
2. **Escolhe o tema** de cada oferta *incoming* — dropdown restrito aos temas listados na oferta
   (regra: quem recebe escolhe). Sem tema escolhido, não dá para aceitar a oferta.
3. **Aceita ou recusa POR SEMANA/oferta** — recusar existe justamente para equilibrar o balanço
   ("se ele envia 2 e pega 3, posso recusar 1 com o objetivo de trocar com outra congregação").
   Semana recusada volta a ficar disponível para o convite circular (round-robin já existente).
4. **Confirma** o que aceitou → gera as designações:
   - *incoming* aceita → `TalkAssignment` na NOSSA semana (slot da casa) com orador visitante e
     o tema escolhido; slot sai de "em aberto".
   - *outgoing* aceita → registro de saída do NOSSO orador na semana DELES (modelo/convensão de
     saída já usado no módulo — conferir como `direction=outgoing` é materializado hoje; o
     dia/horário concreto vem de `meeting_weekday`/`meeting_time` da congregação parceira e SÓ
     aparece na impressão/ao notificar o orador).
   - Notificações WhatsApp: orador nosso designado recebe o aviso de saída (fluxo de
     opt-in/notificação de designação já existente — reusar `SendSpeakerAssignmentNotification`);
     coordenadora convidada recebe um resumo do que foi confirmado/recusado.
5. **Mensagens livres**: painel lateral com as `ExchangeMessage` do send, e ação "criar oferta a
   partir desta mensagem" (pré-preenche semana/orador manualmente) — cobre o caso "ele manda
   direto no WhatsApp e eu programo manualmente".

## Onde construir

Já existe `resources/js/pages/publicTalks/ExchangeWorkbench.vue` (mesa). **Evoluir a mesa
existente**, não criar página nova — conferir o que ela já mostra (mensagens? ofertas?) e
acrescentar a montagem. Backend: controller/service correspondente (procurar por
`ExchangeWorkbench`/`Workbench` em `app/Http/Controllers/PublicTalks/` e `app/Services/PublicTalks/`).

## Fases

### Fase A — Backend de decisão

- Enum `ExchangeOfferStatus`: garantir estados `Draft → Accepted | Declined` (conferir os já
  existentes antes de criar novos).
- Service (ex.: `ExchangeAssembler` em `app/Services/PublicTalks/`) com:
  - `chooseOutline(offer, outline)` — valida que o outline pertence à oferta (incoming);
  - `accept(offer)` — valida tema escolhido (incoming), materializa a designação;
  - `decline(offer)` — devolve a semana ao pool aberto (incoming) e libera o orador (outgoing);
  - `confirm(send)` — fecha o pacote: notifica convidada com resumo, dispara notificações aos
    oradores nossos (outgoing), marca o send conforme convenção de status atual.
- Endpoints Inertia no controller da mesa (aceitar/recusar/escolher tema/confirmar).
- Transação por ação; idempotência (aceitar 2× não duplica designação).

### Fase B — Frontend da mesa

- Agrupamento por semana com os dois lados lado a lado; balanço no topo (enviam × levam).
- Card incoming: orador deles, telefone, chips dos temas → select do tema + botões
  Aceitar/Recusar.
- Card outgoing: orador nosso, semana deles (com dia/horário derivado do cadastro da parceira,
  exibido como informação), tema que ELES escolheram (somente leitura) + Aceitar/Recusar.
- Painel de mensagens livres do send + "criar oferta a partir da mensagem" (form manual).
- Botão **Confirmar troca** (desabilitado enquanto houver incoming aceito sem tema).

### Fase C — Pós-confirmação

- Semanas recusadas: garantir que o round-robin (`ExchangeRoundRobin`) volte a oferecê-las à
  próxima congregação da lista (conferir o gatilho atual de "semana aberta").
- Impressão/envio ao orador: usar dia/horário da congregação de destino (outgoing) — conferir
  se o formatter da melhoria 1 já cobre; ajustar se necessário.

## Testes (Pest, obrigatórios)

- escolher tema fora da lista da oferta → erro;
- aceitar incoming sem tema → erro; com tema → `TalkAssignment` criado no slot da semana, slot fecha;
- aceitar outgoing → saída materializada + notificação ao orador nosso (fake WhatsApp);
- recusar incoming → semana volta ao pool (visível para round-robin);
- confirmar send com 2 aceitas + 1 recusada → resumo enviado à convidada com aceitas E recusada;
- aceitar 2× a mesma oferta → não duplica;
- criar oferta manual a partir de `ExchangeMessage` → oferta Draft ligada à `source_message_id`.
- Rodar suite PublicTalks afetada + `vendor/bin/pint --dirty --format agent`.
