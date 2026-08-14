# Melhoria 2 — Apresentação no primeiro contato, opt-in de trocas e reativação

> Doc autossuficiente para ser executado por um **chat separado**.
> Antes de codar: leia `00-INDICE.md`, `melhoria-1-semana-e-horarios.md` (se já executada) e as
> regras em `.ai/rules/` — em especial `inbound.md` (**todo inbound passa pelo
> `InboundDispatcher::HANDLERS`; nunca criar listener paralelo**; botões roteiam por correlação de
> `wamid`, nunca pelo rótulo) e a terminologia: **troca(s)**, nunca "permuta".

## Pare e pense no fluxo primeiro

Este não é um recurso isolado: é a **porta de entrada do relacionamento** entre congregações.
Antes de escrever código, desenhe a máquina de estados completa (papel/markdown) e valide-a
contra os cenários da seção "Conversa de exemplo". Perguntas que seu desenho precisa responder:

1. O que impede um convite de troca de ir para quem **nunca recebeu a apresentação**?
2. O que acontece se a congregação **não responde** à apresentação? (não é "não" — é `unknown`)
3. Como o sistema sabe que uma mensagem recebida vem de uma congregação `opted_out`
   e propõe **reativação** em vez de tratá-la como resposta de convite?
4. Como diferenciar "mudei de ideia, aceito trocas" de "assunto diverso" (que deve ir para o
   coordenador humano)? Resposta: **não interpretar texto livre por IA** (regra inviolável do
   índice) — oferecer **botões** e, no texto livre, encaminhar ao coordenador.

## Estado atual (o que já existe — reutilizar, não duplicar)

- `congregations.exchange_opt` enum `opted_in|opted_out|unknown` (`App\Enums\ExchangeOpt`) —
  hoje é editado só manualmente no cadastro.
- `ExchangeRoundRobin` — sugestão da próxima congregação do rodízio (já filtra por opt).
  Garantir: `unknown` e `opted_out` **não entram** no rodízio de convites; `unknown` entra na
  fila de **apresentação**.
- `InboundDispatcher::HANDLERS` (ordem = precedência):
  `SpeakerButtonHandler → CoordinatorConversationHandler → PartnerReplyHandler → SpeakerFreeTextHandler → SafetyNetHandler`.
- `exchange_messages` guarda histórico íntegro; `coordinators` tem o responsável do time (telefone).
- Templates WhatsApp em `database/whatsapp-templates/` — **mensagem iniciada pelo negócio exige
  template aprovado pela Meta**. Sandbox primeiro; nada é submetido/enviado sem aprovação do usuário.

## Objetivo

1. **Template de apresentação** (`coordena_intro`): primeiro contato com congregação nunca
   contatada. Conteúdo: quem somos (congregação-casa + coordenador), o que é o Coordena com
   **link de acesso ao sistema** (portal público com token — reutilizar o mecanismo de
   `portal_token` da fase 2), e a pergunta: *aceita fazer trocas de oradores conosco?*
   Botões: **"Sim, aceito"** / **"Agora não"**.
2. **Novo modelo de rastreio** `congregation_intros` (ou campos em tabela própria — decidir no
   desenho): `congregation_id`, `team_id`, `status`
   (`pending|sent|accepted|declined|expired`), `wamid?` unique, `sent_at`, `responded_at`,
   `declined_at?`, `reactivated_at?`, timestamps. É o registro auditável de "já nos apresentamos?".
3. **Gate no fluxo de convite**: enviar convite de troca só para `opted_in`. Se a congregação é
   `unknown`, a UI oferece "Enviar apresentação" no lugar de "Enviar convite".
4. **Resposta aos botões** (novo `IntroButtonHandler` inserido na posição correta do array
   `HANDLERS` — provavelmente antes de `PartnerReplyHandler`; justifique a posição no PR e
   atualize o teste de contrato `InboundDispatcherTest`):
   - **Sim** → `exchange_opt = opted_in`, intro `accepted`, resposta de boas-vindas; congregação
     passa a entrar no rodízio.
   - **Agora não** → `exchange_opt = opted_out`, intro `declined`, resposta cordial informando
     que **pode nos chamar se mudar de ideia** (fica registrado) e deixando o **telefone do
     coordenador** para qualquer outro assunto.
5. **Reativação**: mensagem recebida de congregação `opted_out` (fora de qualquer convite vivo)
   → novo `ReactivationHandler`: registra a mensagem, responde propondo reativação com botões
   **"Voltar a fazer trocas"** / **"Falar com o coordenador"**.
   - "Voltar a fazer trocas" → `opted_in` + `reactivated_at` + confirmação.
   - "Falar com o coordenador" ou **texto livre/assunto diverso** → encaminhar a mensagem
     **íntegra** ao coordenador responsável (mecanismo já existente de encaminhamento do
     `SafetyNetHandler`/forward) **e** responder à congregação com o número do coordenador para
     contato direto. Nunca tentar interpretar o assunto.
6. Tudo registrado: cada passo grava em `exchange_messages` (ou tabela de mensagens da intro,
   coerente com o desenho) — o histórico íntegro é regra do produto.

## Conversa de exemplo (use como roteiro de testes)

```text
[Coordena → Congregação Norte]  (template coordena_intro)
Olá! Aqui é Carlos, coordenador de discursos da congregação Capão Novo (Capão da Canoa RS).
Usamos o Coordena para organizar a programação de discursos públicos e as trocas de oradores.
Você pode ver nossa programação aqui: https://coordena.app/p/abc123
Vocês teriam interesse em fazer trocas de oradores com a nossa congregação?
[Sim, aceito] [Agora não]

— Cenário A (aceite) —
[Norte] toca botão "Sim, aceito"
[Coordena] Que ótimo! Vamos incluir a congregação de vocês nas nossas trocas.
Em breve enviaremos o convite do mês. Qualquer coisa, o Carlos atende no +55 51 9xxxx-xxxx.
(sistema: exchange_opt=opted_in, intro=accepted → entra no rodízio)

— Cenário B (recusa) —
[Norte] toca botão "Agora não"
[Coordena] Sem problemas, obrigado por responder! Se mudarem de ideia, é só mandar uma
mensagem aqui que reativamos. Para outros assuntos, o Carlos atende no +55 51 9xxxx-xxxx.
(sistema: exchange_opt=opted_out, intro=declined → fora do rodízio)

— Cenário C (reativação, semanas depois) —
[Norte] "Oi, conversamos aqui e queremos participar das trocas sim"
[Coordena] Olá! Vi que vocês tinham preferido não participar das trocas. Querem reativar?
[Voltar a fazer trocas] [Falar com o coordenador]
[Norte] toca "Voltar a fazer trocas"
[Coordena] Reativado! Vocês voltam a receber nossos convites de troca. 🎉
(sistema: opted_in, reactivated_at preenchido, tudo em exchange_messages)

— Cenário D (assunto diverso) —
[Norte] "Preciso falar sobre a assembleia de circuito"
[Coordena] Esse assunto é com o coordenador: fale direto com o Carlos no +55 51 9xxxx-xxxx.
Já encaminhei sua mensagem para ele também.
(sistema: forward íntegro ao coordenador; nada é interpretado)
```

## Plano de execução (ordem sugerida)

1. Desenho da máquina de estados + decisão do modelo (`congregation_intros`) — documentar no PR.
2. Migration + model + enum de status da intro; gate no `ExchangeRoundRobin`/tela de convite.
3. Template `coordena_intro` em `database/whatsapp-templates/` + textos em
   `lang/pt_BR/app/public_talks.php` (zero texto fixo). **Testar no sandbox antes; submissão à
   Meta só com aprovação explícita do usuário.**
4. UI: botão/fluxo "Enviar apresentação" para congregação `unknown` (mobile first); status
   visível no cadastro da congregação (aceitou / recusou em :data / aguardando resposta).
5. `IntroButtonHandler` + `ReactivationHandler` no array `HANDLERS` (atualizar teste de contrato).
6. Testes Pest cobrindo os 4 cenários da conversa de exemplo + gate do rodízio + idempotência
   de botão repetido. Regras: rotas por `slug`; comparação de datas com Carbon (`.ai/rules`).

## Critérios de aceite

- [ ] Congregação `unknown` nunca recebe convite de troca; recebe apresentação primeiro.
- [ ] "Sim" → `opted_in` + entra no rodízio; "Agora não" → `opted_out` + fora do rodízio.
- [ ] Recusa recebe mensagem com "pode mudar de ideia" + telefone do coordenador.
- [ ] Mensagem de `opted_out` dispara proposta de reativação com botões; aceite reativa e registra.
- [ ] Texto livre/assunto diverso vai íntegro ao coordenador + resposta com o número dele.
- [ ] Todo o histórico fica registrado (mensagens + timestamps de aceite/recusa/reativação).
- [ ] Nenhum template enviado à Meta sem aprovação do usuário; sandbox primeiro.
- [ ] Pint limpo, testes passando, i18n completo, mobile first.

## Fora de escopo

- Interpretação de texto livre por IA (regra inviolável — evolução futura registrada no índice).
- Opt-in/apresentação iniciados pela própria congregação parceira sem contato prévio nosso.
