# Fase 2 — Permuta: convite mensal, mesa de trabalho da resposta e portal público

Depende de: fases 0 e 1. Tamanho: G (a maior fase — pode ser dividida em 2 PRs: domínio+mesa /
portal). **Leia antes: `00-INDICE.md`** (modelo de dados §Permuta e regras invioláveis).
Sem WhatsApp nesta fase: envio é registrado como `manual` (o coordenador copia o texto) e a
resposta entra colada na mesa de trabalho ou pelo portal. A fase 3 pluga o canal.

## O fluxo do convite (validado com o usuário — o v1 fazia isso bem, PRESERVAR)

1. **1 convite por time+mês**, cobrindo as semanas em falta daquele mês. Não é recriado.
2. O coordenador **envia** o convite a uma congregação da lista (rodízio de parceiras
   `opted_in`) → cria `exchange_invite_send`.
3. A congregação responde preenchendo **parte** das semanas (ex.: 2 de 4). As restantes
   continuam abertas **no mesmo convite**, que é enviado à **próxima** congregação da lista.
4. Repete até todas as semanas do mês estarem preenchidas (`status = filled`).
5. Reciprocidade (mão dupla): o convite enviado inclui a **nossa** lista de oradores
   disponíveis + temas para aquele mês (`SpeakerAvailability::availableFor()` da casa),
   para a convidada escolher também (ofertas `outgoing`).

## A mesa de trabalho (a correção central do v1 — o processamento da resposta era "muito ruim")

Problema v1: a modal só deixava cadastrar 1 oferta e fechava; para cada oferta era preciso
reabrir/reenviar. Aqui: **ler a mensagem e cadastrar N ofertas numa sessão**, com resposta parcial.

UX mobile (validada):

```
┌──────────────────────────────┐
│ Convite Nov/2026 · Central   │
├──────────────────────────────┤
│ 💬 Resposta recebida          │  ← cartão colapsável FIXO no topo (mensagem íntegra,
│ "Temos o Pedro com temas     │     histórico de exchange_messages do envio)
│  12 e 43, e o Marcos..."  ▼  │
├──────────────────────────────┤
│ Ofertas cadastradas (2)      │
│ • Pedro M. · temas 12, 43    │
│   → Sáb 14/11        [editar]│
│ • Marcos L. · tema 88        │
│   → sem semana       [editar]│
│ [ + Cadastrar oferta ]       │
├──────────────────────────────┤
│ Semanas do mês               │
│ 07/11 ✓casa · 14/11 Pedro    │
│ 21/11 livre · 28/11 livre    │
├──────────────────────────────┤
│ [ Confirmar e responder ]    │  ← ÚNICO ponto que envia algo de volta
└──────────────────────────────┘
```

- **+ Cadastrar oferta** (bottom sheet): busca orador **da congregação do envio** (escopo
  automático — sem seletor de congregação); se não existir, atalho "cadastrar novo orador"
  cria o `Speaker` ligado àquela congregação (permissão `public-talks:manage` basta — regra
  da fase 0) → temas (multi, catálogo) → semana-alvo (opcional) → salva `draft` → volta com
  a mensagem ainda no topo. Repetir quantas vezes a mensagem render.
- Ofertas `draft` sem semana podem ser atribuídas depois (chip da semana → escolher oferta).
- Desktop/tablet: mensagem vira coluna esquerda; resto coluna direita.

## Entregas

### 1. Serviços de domínio

- [ ] `ExchangeInviteManager`: `forMonth(Team, month)` (firstOrCreate do convite),
  `openWeeks()`, recalcular `status` (`open|partially_filled|filled|expired`).
- [ ] `ExchangeRoundRobin`: próxima parceira candidata (opted_in, com contato, sem envio
  vivo deste convite, menos recentemente convidada). Sugestão, não imposição — o coordenador
  escolhe/envia manualmente.
- [ ] `InviteComposer`: monta o texto do convite (semanas em falta + nossa lista de
  oradores/temas do mês, formato multi-linha legível) — usado no envio manual agora e
  pelo WhatsApp na fase 3. Formato de referência do v1 (fase 7): bloco de datas com
  `Orador: __ / Temas: __`; lista `nome - papel: telefone?` + `número - título` por linha.
- [ ] `ExchangeConfirmer::confirm(ExchangeInviteSend, offers[])` — **caminho único** de
  confirmação: valida `SpeakerAvailability` de novo, grava ofertas `confirmed`, cria/atualiza
  `talk_assignments` (`incoming` na nossa grade; `outgoing` quando a convidada escolheu dos
  nossos), monta o resumo de resposta (registrado em `exchange_messages` outbound) e fecha
  as semanas. Nada fora daqui promove `draft → confirmed`.

### 2. UI do convite

- [ ] Na programação (fase 1), semanas em falta do mês linkam para o convite do mês.
- [ ] Tela do convite: semanas em falta, envios já feitos (linha do tempo: congregação,
  status, quando), botão "Enviar para próxima" (mostra sugestão do rodízio, permite trocar)
  → registra `send` com texto do `InviteComposer` pronto para copiar (canal `manual`).
- [ ] Registrar resposta recebida manualmente: colar o texto → vira `exchange_message`
  inbound do envio → abre a mesa de trabalho.

### 3. Mesa de trabalho

- [ ] Página Inertia mobile-first conforme wireframe; CRUD de ofertas `draft` +
  atribuição de semana; validação de disponibilidade ao selecionar semana.
- [ ] "Confirmar e responder" → `ExchangeConfirmer` → resumo exibido (e copiável) —
  convite parcial continua aberto para o próximo envio.
- [ ] Também trata as escolhas da convidada sobre **nossos** oradores (ofertas `outgoing`
  selecionadas por ela chegam como texto → cadastráveis do mesmo jeito).

### 4. Portal público (caminho estruturado da convidada)

- [ ] Rota pública assinada por `portal_token` do envio (sem login, rate-limited):
  a convidada vê as semanas em falta + nossa lista de oradores/temas; preenche seus
  oradores (nome, papel, telefone?, temas) por semana **ou** sem semana; escolhe dos nossos.
- [ ] Submissão do portal cria `exchange_message` (channel `portal`, corpo = resumo
  estruturado) **e** ofertas `draft` já estruturadas — a mesa de trabalho vira revisão.
  **Nunca** confirma sozinha (regra inviolável 1).
- [ ] Oradores do portal que não existem: criados `is_active` ligados à congregação do envio
  ao serem usados numa oferta confirmada (antes disso ficam como dados da oferta pendentes
  de materialização — decidir na implementação e documentar; o invariante é: `talk_assignment`
  só nasce com `speaker_id` real).

## Testes

- [ ] `ExchangeInviteManagerTest`: 1 convite por mês; status recalcula; semanas restantes após
  confirmação parcial.
- [ ] `ExchangeRoundRobinTest`: rodízio, opt-out fora, sem duplicar envio vivo.
- [ ] `ExchangeConfirmerTest`: draft→confirmed só pelo serviço; assignments criados;
  disponibilidade revalidada (orador que ficou indisponível entre draft e confirm → erro claro).
- [ ] Feature mesa de trabalho: cadastrar 3 ofertas numa sessão sem recarregar; oferta sem
  semana; criar orador inline escopado à congregação do envio.
- [ ] Feature portal: token inválido/expirado; submissão cria drafts; não confirma nada.
- [ ] Browser mobile: fluxo completo colar resposta → 2 ofertas → confirmar → semanas fecham.

Rodar `vendor/bin/pint --dirty --format agent`; `npm run build`.

## Critério de aceite

- Um mês com 4 semanas em falta: envio manual à congregação A, resposta com 2 ofertas
  processada numa única sessão da mesa, confirmação fecha 2 semanas; mesmo convite segue
  para a congregação B com as 2 restantes; mês termina `filled`.
- Pelo portal, a convidada preenche sozinha e o coordenador só revisa e confirma.
