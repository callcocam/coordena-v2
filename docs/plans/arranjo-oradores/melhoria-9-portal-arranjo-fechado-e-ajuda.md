# Melhoria 9 — Portal da permuta: arranjo fechado, dicas e página de ajuda

> Plano autossuficiente para ser executado em um **chat separado**. Antes de codar: leia
> `00-INDICE.md`, este arquivo e as regras em `.ai/rules/` (tela →
> `resources/js/pages/publicTalks/ExchangePortal.vue` via `pages.md`; controller →
> `app/Http/Controllers/PublicTalks/ExchangePortalController.php` via `controllers.md` +
> `app.md`; testes → `tests.md`). Rode `grep -rin 'keyword' .ai/rules` para o resto.

## Pedido do dono (2026-08-14, literal)

"nessa tela onde processa o arranjo deveria listar o arranjo fechado, até o link expirar,
e algumas dicas sobre o coordena, talvez uma lista com os temas que já foram proferidos
recentemente na congregação dele. um link pra página de ajuda, que vamos criar com várias
dicas e exemplos. no caso ajuda direto ao ponto de programação de discurso — vamos ter
outras atividades no coordena."

## Estado atual (verificado em 2026-08-14 — reconfira antes de editar)

- Tela pública `/permuta/{token}` → `ExchangePortalController@show` renderiza
  `publicTalks/ExchangePortal` com: `openWeeks`, `monthWeeks`, `homeSpeakers` (expostos),
  `partnerSpeakers`, `outlineCatalog`, `closed`.
- `closed` = `isClosed()` → só `Declined|Expired` (`ExchangeInviteSendStatus`); o Vue
  mostra um banner `data-test="portal-closed"` e some com o form.
- Pós-envio: `store` retorna `back()->with('portal_submitted', true)` → tela de
  agradecimento; **revisitar o link mostra o formulário vazio de novo** — nada do que foi
  enviado nem do que a mesa decidiu aparece para a congregação convidada.
- Ofertas: `ExchangeOffer` por send/semana com `ExchangeOfferStatus`
  (`Draft|Selected|Accepted|Declined|Confirmed|Discarded`). A mesa (melhoria 5) decide;
  o portal nunca confirma.
- Expiração: `NudgePendingInviteSends` marca `ExchangeInviteSendStatus::Expired`.
- Não existe página de ajuda pública nem rota `/ajuda/*`.

## Escopo

### Fase A — Backend: payload do portal

1. `show`: incluir `arrangement` — as ofertas já registradas deste `send` (as que a
   congregação enviou e as que a mesa montou), agrupadas por direção:
   - por item: `week`, `direction` (`incoming|outgoing` do ponto de vista DELES —
     cuidado: no nosso domínio é invertido; nomear pensando no leitor do portal),
     `speaker_name`, `outline {number,title}`, `status` traduzível
     (aguardando revisão / aceito / confirmado / recusado / descartado).
   - Visível **enquanto o link resolver** (`findSend` já dá 404 depois que sumir; itens
     aparecem também com `closed=true` — o banner de fechado convive com o histórico).
2. `show`: incluir `recentOutlines` — temas proferidos recentemente NA congregação
   convidada, pelo que o Coordena conhece: `TalkAssignment` do time com
   `counterpartCongregation` = congregação do send, tipo outgoing (nosso orador foi lá)
   + ofertas `Confirmed` de sends anteriores para ela. Últimos ~6 meses, ordenado
   decrescente, campos `date`, `outline {number,title}`, `speaker_name`. Deixar claro na
   UI que é "pelo que passou pelo Coordena" (não é o histórico completo deles).
3. `show`: incluir `helpUrl` (rota nomeada da Fase C) e, se barato, `expiresAt` para a
   UI dizer até quando o link vale (ver onde o nudge calcula o prazo).

### Fase B — Front: `ExchangePortal.vue` + lang

1. Seção "Arranjo desta troca" (quando `arrangement` não vazio): lista por semana com
   orador, tema e badge de status; substitui a tela de agradecimento seca — pós-envio
   mostra o resumo do que acabou de ser enviado (estados `draft` = aguardando revisão).
2. Seção "Temas recentes na sua congregação" (quando `recentOutlines` não vazio):
   lista compacta (data · nº tema · orador) — serve de guia para não repetir tema.
   O backend já bloqueia repetição de tema/orador (regras existentes); aqui é UX.
3. Bloco "Dicas do Coordena": 2–3 dicas curtas direto ao ponto (ex.: escolher tema que o
   orador profere; semanas em branco são opcionais; a resposta vai para a mesa do
   coordenador) + link "Ver guia completo" → página de ajuda.
4. Novas chaves em `lang/pt_BR/app/public_talks.php` (seção `portal`).

### Fase C — Página de ajuda pública

1. Rota pública sem auth, por área (o Coordena terá outras atividades):
   `/ajuda/discursos-publicos` (nome de rota `help.public-talks`) — estrutura pronta
   para `/ajuda/{outras-areas}` depois. Sem token, sem dados de time.
2. Página Inertia `resources/js/pages/help/PublicTalks.vue` (seguir convenção de layout
   das páginas públicas existentes, ex.: `ExchangePortal.vue` / `IntroPortal.vue` —
   layout no `defineOptions` da página, ver memória do projeto).
3. Conteúdo pt-BR direto ao ponto da programação de discursos, com exemplos:
   como funciona o convite de troca, como preencher o portal (com prints/exemplos
   textuais), o que acontece depois do envio (mesa → confirmação → WhatsApp), regras
   (quem recebe escolhe o tema; 1 saída por mês por orador; não repetir orador/tema),
   perguntas frequentes. Conteúdo em lang file ou direto na página — seguir o padrão
   que `.ai/rules/pages.md` indicar; na dúvida, texto via chaves lang.
4. Link para a ajuda no portal (Fase B) e no `IntroPortal` se fizer sentido barato.

### Fase D — Testes (Pest)

- `show` expõe `arrangement` com ofertas do send (e não vaza ofertas de outros sends),
  `recentOutlines` só da congregação do token, `helpUrl`.
- Pós-`store`, um novo GET do portal traz o que foi submetido como `draft`.
- Send `Expired|Declined`: `closed=true` E `arrangement` ainda visível.
- Rota de ajuda responde 200 sem autenticação.
- Rodar: `php artisan test --compact --filter=ExchangePortal` + teste novo da ajuda;
  `vendor/bin/pint --dirty --format agent`; `npm run build`.

## Decisões a confirmar com o dono no início do chat

1. O arranjo listado inclui só o que a MESA já processou, ou também o rascunho recém
   enviado? Sugerido: ambos, com status distintos (transparência).
2. Janela dos "temas recentes": 6 meses é bom? E mostrar o nome do orador ou só o tema?
3. Conteúdo da página de ajuda: o dono quer revisar o texto antes de publicar?
   (Gerar rascunho no plano de execução e pedir aprovação.)

## Fora de escopo

- Ajuda de outras áreas do Coordena (só deixar a estrutura de rota preparada).
- Qualquer edição do arranjo pelo portal depois do envio (continua papel da mesa).
