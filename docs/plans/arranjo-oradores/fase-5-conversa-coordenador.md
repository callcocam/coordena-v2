# Fase 5 — Conversa do coordenador pelo WhatsApp (menu guiado)

Depende de: fase 4 (dispatcher — slot 2 já reservado). Tamanho: M/G.
**Leia antes: `00-INDICE.md`**. Parte do core do produto: o coordenador opera sem abrir o painel.

## Escopo

Somente o **coordenador responsável e ajudantes** (remetentes conhecidos de `coordinators`).
Fluxo guiado por menu — **sem IA, sem texto livre interpretado**. Toda ação chama exatamente
os mesmos serviços do painel (fases 1–3); a conversa é só uma casca.

## Máquina de estados

- [ ] Tabela `whatsapp_conversations`: `team_id`, `phone` (normalizado), `coordinator_id`,
  `state`, `context` json, `last_message_at`, `expires_at` (janela 24h). 1 conversa aberta
  por telefone+time; expirada → próxima mensagem recomeça no menu.
- [ ] Plugada no **slot 2 do InboundDispatcher** (fase 4): remetente é coordenador do time
  resolvido pelo telefone → despacha para o handler do estado atual; entrada não reconhecida
  re-apresenta as opções do estado (nunca erro seco).
- [ ] Menus: list message interativa quando disponível; aceitar também número digitado;
  fallback em menu numerado de texto se o envio interativo falhar. Opções enviadas ficam em
  `context['options']` para o retorno significar a mesma ação.

## Estados desta entrega

| Estado | Comportamento |
|---|---|
| `menu` | opções: **Programação da semana**, **Permutas pendentes**; (demais ideias ficam como stub "em breve") |
| `week_view` | resumo do fim de semana corrente (e opção "próxima semana"): compromissos + estado de confirmação de cada orador; se há não-notificados/não-confirmados → oferece disparo |
| `confirm_dispatch` | "sim" → despacha `SendSpeakerAssignmentNotification` para cada pendente e confirma; "não" → menu |
| `exchange_view` | convites do horizonte com semanas em falta + respostas não processadas; responde com o **link da mesa de trabalho** (a mesa é mobile — o coordenador resolve no celular) |

Fechamento do ciclo (já entregue nas fases 3/4): orador confirma → coordenador é avisado na
mesma conversa; texto livre de orador chega encaminhado — o coordenador age pelo menu ou painel.

## Entregas

- [ ] Model/migration + `ConversationEngine` (transições declarativas: estado → opções → ação).
- [ ] Handlers dos 4 estados chamando os serviços existentes (nenhuma regra de negócio nova aqui).
- [ ] Textos variados e cordiais em `lang/pt_BR/` (zero string fixa).
- [ ] Idempotência por wamid; log da conversa consultável (reusar tabela de inbound + outbound).

## Testes

- [ ] `ConversationFlowTest`: "oi" abre menu; navegação até `week_view`; disparo despacha os
  jobs certos (`Bus::fake()`); entrada inválida re-apresenta; expiração reinicia no menu;
  remetente desconhecido NÃO abre conversa (cai nos slots seguintes do dispatcher);
  botão de notificação de orador NÃO é capturado pela conversa (precedência do slot 1).
- [ ] Regressão da suite de inbound da fase 4.

Rodar `vendor/bin/pint --dirty --format agent`.

## Critério de aceite

Coordenador manda "oi" → menu → "programação da semana" → recebe estados → "sim" → oradores
pendentes notificados → orador confirma → coordenador avisado. Tudo sem abrir o painel.
