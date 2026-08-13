# Fase 4 — Inbound determinístico: dispatcher único

Depende de: fase 3. Tamanho: M. **Leia antes: `00-INDICE.md`** (lições do v1 §1) e o webhook
inbound já existente no v2 (`whatsapp_inbound_messages`).

## Lição do v1 (arquitetural)

No v1, cada fluxo adicionou um listener de webhook; a ordem entre eles era sensível e virou
fonte de bugs, até precisar de um "árbitro" tardio. No v2 **não existem listeners
concorrentes**: existe **um** ponto de despacho com cadeia de handlers em precedência
explícita, declarada num único lugar e testada como contrato.

## O dispatcher

`App\Services\PublicTalks\Inbound\InboundDispatcher` — recebe a mensagem inbound persistida
e percorre, **nesta ordem**, handlers com interface comum (`matches()` / `handle()`), parando
no primeiro que tratar:

1. **Correlação por wamid** (`context.id` da resposta ↔ `wamid` gravado): botões de
   notificação do orador; futura correlação de sends de permuta. Nunca rotear botão só por rótulo.
2. **Conversa aberta do coordenador** (fase 5 pluga aqui; nesta fase o slot existe e não casa).
3. **Parceira com envio de convite vivo** (telefone normalizado ↔ contato da congregação de um
   `exchange_invite_send` `sent|answered` recente): registra `exchange_message` inbound íntegra,
   marca o send `answered`, avisa o responsável ("Resposta da Cong. X — abrir mesa de trabalho: {link}").
   **Sem parsing** — o texto vai para a mesa de trabalho.
4. **Orador conhecido com notificação viva** (telefone ↔ speaker com notification < N dias):
   encaminha o corpo **íntegro** a `ResponsibleCoordinator::recipientsFor()`
   ("O orador X, discurso de DD/MM, respondeu: ..."). Sem IA.
5. **Rede de segurança**: mensagem não reconhecida → encaminhar ao responsável (com throttle
   por remetente para não virar spam).

Idempotência por wamid (`Cache::add`) em todos. Precedência declarada num array único no
dispatcher — o teste de contrato falha se alguém inserir handler sem definir posição.

## Entregas

- [ ] Dispatcher + interface + registro (plugado no fluxo de webhook existente do v2).
- [ ] Handler 1: botão do orador — `Tudo certo` → notification `confirmed`, assignment
  `confirmed`, ack ao orador (sessão), aviso ao coordenador; `Preciso remarcar` → notification
  `declined`, assignment `needs_reschedule`, aviso ao coordenador.
- [ ] Handler 3: resposta de parceira → mesa de trabalho (link direto) + aviso.
- [ ] Handler 4: texto livre de orador → encaminhamento íntegro.
- [ ] Handler 5: rede de segurança com throttle.
- [ ] UI: badges da fase 3 refletem confirmação/remarcação (payload da programação).

## Testes

- [ ] `InboundDispatcherTest` (contrato): ordem de precedência; primeiro match ganha; mensagem
  idempotente por wamid; nenhum handler rouba botão de outro fluxo (rótulos exclusivos).
- [ ] Feature por handler: confirmação reflete no assignment + avisa coordenador; resposta de
  parceira vira `exchange_message` e o link da mesa chega ao responsável; texto do orador chega
  íntegro a responsável + ajudantes; desconhecido cai na rede de segurança com throttle.

Rodar `vendor/bin/pint --dirty --format agent`.

## Critério de aceite

- Orador toca "Tudo certo" → slot `confirmed` na tela + coordenador avisado.
- Parceira responde o convite por texto → coordenador recebe o link e a mensagem está íntegra
  no topo da mesa de trabalho.
- Suite inteira de inbound verde com a ordem documentada em um único arquivo.
