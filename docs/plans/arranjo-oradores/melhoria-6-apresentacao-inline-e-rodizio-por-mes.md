# Melhoria 6 — Apresentação inline na tela de troca + rodízio ciente do horizonte

> Doc autossuficiente para ser executado por um **chat separado**.
> Antes de codar: leia `00-INDICE.md`, as regras em `.ai/rules/` (em especial
> `controllers.md` — parâmetro posicional `string $current_team` — e `app.md` — comparação
> de colunas `date` com Carbon) e a terminologia: **troca(s)**, nunca "permuta".
> Rode `grep -rin 'round\|rodizio\|intro' .ai/rules` antes de começar.

## Contexto (o que já existe — reutilizar, não duplicar)

- Tela de troca: `resources/js/pages/publicTalks/Exchange.vue` +
  `App\Http\Controllers\PublicTalks\ExchangeController::index`. Ela já recebe:
  - `candidates` — rodízio (`ExchangeRoundRobin::candidatesFor`, só `opted_in`);
  - `pendingIntro` — congregações com contato e `exchange_opt = unknown`
    (`ExchangeRoundRobin::pendingIntroFor`), exibidas na seção **"Aguardando apresentação"**
    quando o rodízio está vazio (`data-test="pending-intro"`).
- Envio da apresentação: `POST acervo/{congregation}/apresentacao`
  (`acervo.congregations.intro.store` → `CongregationIntroController::store`), que:
  - exige `contact_phone` normalizável (senão toast de erro);
  - recusa se já existe `CongregationIntro` `Pending|Sent` para o par time+congregação
    (toast `intro.already_pending`);
  - cria `CongregationIntro` e despacha `SendCongregationIntro`; retorna `back()`.
- Rodízio: `ExchangeRoundRobin::candidatesFor` exclui congregações com envio **vivo no
  convite do próprio mês** (`Pending|Sent|Accepted|Answered`) e ordena por "menos
  recentemente convidada" (`lastInvitedAtByCongregation`, que olha TODOS os convites do time).
- Horizonte: 3 meses (`ScheduleHorizon`); a tela de troca tem um seletor de mês (`months`),
  então **vários meses podem estar abertos ao mesmo tempo** durante a primeira rodada.

## Objetivo

### A) Botão "Enviar apresentação" inline na lista "Aguardando apresentação"

Hoje o coordenador precisa navegar até o cadastro da congregação no acervo para enviar a
apresentação (screenshot do usuário: card "Apresentação / Sem contato prévio" no Show do
acervo). Queremos o mesmo botão **direto em cada item** da lista "Aguardando apresentação"
da tela de troca, sem sair da página.

1. `ExchangeController::index` deve enriquecer cada item de `pendingIntro` com:
   - `has_whatsapp` (via `Phone::normalize($congregation->contact_phone) !== null`);
   - `intro_status`: `null` (nunca enviada) ou o status da `CongregationIntro` mais recente
     do par time+congregação (reutilizar o scope `forPair`). Basta distinguir
     "aguardando resposta" (`Pending|Sent`) dos demais.
2. Em `Exchange.vue`, cada `<li>` da lista vira: nome/cidade (mantém o link para o Show do
   acervo) + à direita:
   - botão **"Enviar apresentação"** (`router.post` para `acervo.congregations.intro.store`,
     `preserveScroll`) quando `has_whatsapp` e não há intro aguardando;
   - badge **"Aguardando resposta"** (desabilitado, sem botão) quando `Pending|Sent`;
   - badge **"Sem WhatsApp"** quando `!has_whatsapp` (reutilizar chave
     `exchange.no_whatsapp`).
3. Após o post, o `back()` do controller já recarrega a página; a congregação passa a
   mostrar "Aguardando resposta". Toasts existentes do `CongregationIntroController` cobrem
   sucesso/erro — não duplicar mensagens.
4. i18n: novas chaves em `lang/pt_BR/app/public_talks.php` (grupo `exchange`), ex.:
   `pending_intro_send` e `pending_intro_waiting`. Zero texto fixo.
5. Mobile first: em viewport estreito o botão não pode quebrar o layout do item
   (empilhar com `flex-wrap` ou coluna; testar 360px).

### B) Rodízio ciente do horizonte: quem já tem convite não repete no mês seguinte

Regra do usuário: *"se a congregação já tiver um convite, não aparecer para convidar no mês
seguinte"*. Durante a primeira rodada o horizonte tem 2–3 meses abertos simultaneamente e a
mesma congregação hoje aparece como candidata em todos. Depois que a primeira rodada fecha o
horizonte inteiro, normalmente só existe um mês em aberto por vez e a regra quase não bite.

1. Em `ExchangeRoundRobin::candidatesFor($invite)`, além do filtro atual (envio vivo no
   convite do próprio mês), **excluir congregações com envio vivo
   (`Pending|Sent|Accepted|Answered`) em convite de QUALQUER outro mês do mesmo time**
   dentro do horizonte. Racional: uma congregação negocia um mês por vez; enquanto o convite
   do mês X está vivo com ela, ela não deve ser sugerida (nem selecionável) para o mês X+1.
2. **Fallback obrigatório (não travar o fechamento do mês):** se essa exclusão zerar a lista
   de candidatas mas existirem congregações `opted_in` com contato (ou seja, todas estão
   ocupadas em outros meses), relaxar a exclusão do item 1 e voltar à lista completa ordenada
   por "menos recentemente convidada". O usuário confirmou: fechada a primeira rodada,
   "geralmente vai ter só um mês pra combinar" — o fallback existe para o caso extremo de
   acervo pequeno.
3. Envios `Declined|Expired` **não** bloqueiam: a congregação volta imediatamente ao rodízio
   (comportamento atual, manter).
4. Atenção `.ai/rules/app.md`: `exchange_invites.month` tem cast `date` — comparações de mês
   sempre com instância Carbon (`$month->startOfMonth()->startOfDay()`), nunca
   `->toDateString()`.
5. A UI não muda nesta parte; apenas a composição de `candidates`/`suggestionId` por mês.
   Trocando o mês no seletor, a lista deve refletir a regra.

## Fora de escopo

- Não mexer no conteúdo/fluxo da apresentação em si (Melhoria 2) nem no convite
  conversacional (Melhoria 3).
- Não criar tela nova; tudo acontece na tela de troca existente.
- Não interpretar resposta por IA.

## Testes (Pest, obrigatórios — `php artisan test --compact` com filtro)

- `ExchangePageTest`:
  - `pendingIntro` expõe `has_whatsapp` e `intro_status`;
  - com intro `Sent` aguardando, o item indica "aguardando" (prop correta).
- Feature: POST da apresentação a partir da tela de troca (rota já existente — cobrir o
  redirect `back()` com `from(...)` apontando para a tela de troca).
- `ExchangeRoundRobinTest`:
  - congregação com envio `Sent` no convite do mês X **não** é candidata no mês X+1;
  - com envio `Declined` no mês X, **é** candidata no mês X+1;
  - fallback: todas ocupadas em outros meses → lista completa volta (ordenada por menos
    recentemente convidada);
  - envio vivo no próprio mês continua excluindo (regressão).
- Rodar também os testes existentes de Exchange (`--filter=Exchange`) antes de finalizar;
  `vendor/bin/pint --dirty --format agent` ao final.

## Critério de aceite

- Na tela de troca, com rodízio vazio, consigo enviar a apresentação de cada congregação
  pendente sem sair da página, e o item passa a "Aguardando resposta".
- Selecionando meses diferentes no seletor, uma congregação com convite vivo no mês X não
  aparece no rodízio do mês X+1 — a menos que não exista nenhuma outra candidata.
- Viewport mobile (360px) sem quebra de layout na lista.
