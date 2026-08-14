# Melhoria 1 — Slot único por semana + dia/horário da reunião em tudo

> Doc autossuficiente para ser executado por um **chat separado**.
> Antes de codar: leia `00-INDICE.md` (regras invioláveis, modelo de dados) e as regras em `.ai/rules/`
> (especialmente `app.md` sobre comparação de colunas `date` com Carbon, e a regra de terminologia:
> o termo do produto é **troca(s)**, nunca "permuta").

## Nota obrigatória — entenda o fluxo do arranjo antes de mexer

Este módulo modela o **arranjo de oradores** das congregações das Testemunhas de Jeová:

- Cada congregação tem **uma única reunião de fim de semana por semana**, num dia e horário
  **fixos e próprios** (ex.: sábado 18h, domingo 9h30). Nela acontece **um** discurso público.
- Portanto a unidade de programação é a **SEMANA**, não o dia. Numa semana existe no máximo
  1 discurso "em casa" — nunca faz sentido listar sábado E domingo da mesma semana como dois slots.
- Congregações **trocam oradores** entre si (o "convite de troca" mensal deste sistema):
  - **Entrada (incoming)**: um orador de outra congregação vem falar **na nossa reunião**,
    ou seja, no **nosso** dia/horário (`meeting_weekday`/`meeting_time` da congregação-casa).
  - **Saída (outgoing)**: nosso orador vai falar na congregação parceira, na reunião **dela**,
    ou seja, no **dia/horário DELA** — que pode ser diferente do nosso.
- Quem organiza é o coordenador de discursos do time; o convite mensal cobre as semanas em
  falta do mês e circula entre as congregações da lista até o mês fechar (ver `00-INDICE.md`).

Se algo aqui parecer ambíguo, releia esta nota: **semana é a chave; o dia/horário concreto é
sempre derivado da congregação onde a reunião acontece.**

## Problema atual (bug + UX)

1. **Tela "Discursos públicos" lista dias, não semanas.** Print real: "Sáb., 15 de Ago." e
   "Dom., 16 de Ago." aparecem como dois cartões da MESMA semana, ambos "Em aberto".
2. **Causa raiz provável** (confirmar antes de corrigir): `App\Services\PublicTalks\ScheduleHorizon::ensure()`
   é idempotente **por data exata** (`whereDate('date', $date)`). Se o `meeting_weekday` da casa
   mudou depois de uma execução (ou era `null` e usou o default `Carbon::SATURDAY`, e depois foi
   definido como domingo), a próxima execução cria um **segundo slot na mesma semana**.
   A idempotência precisa ser **por semana**, não por data.
3. O convite de troca e as telas não comunicam o **dia/horário da reunião**, que é informação
   essencial para a congregação parceira (entrada) e para o nosso orador (saída).

## Objetivo

1. **1 slot "home" por semana por time** — garantido no banco e no serviço.
2. Semana canônica: **segunda-feira como início** (`week_start`, ISO). A data concreta do
   compromisso continua existindo e é derivada:
   - home/incoming → `week_start` + `meeting_weekday`/`meeting_time` da **congregação-casa**;
   - outgoing → `week_start` + `meeting_weekday`/`meeting_time` da **congregação parceira**
     (`counterpart_congregation_id`).
3. Tela da programação mostra **um cartão por semana** ("Semana de 11–17 de ago." ou similar),
   com a data/horário concreto da reunião em destaque.
4. **Convite de troca** (WhatsApp/portal/texto copiado) inclui o dia da semana e o horário da
   nossa reunião em cada semana ofertada (ex.: "15/08 (sáb.) às 18h").
5. Fluxo de **saída** exibe/notifica com o dia/horário da congregação de destino; se a parceira
   não tem `meeting_weekday`/`meeting_time` cadastrados, a UI pede para preencher antes de
   confirmar a saída (ou mostra aviso claro).

## Plano de execução

### 1. Migration + backfill

- Adicionar `week_start` (date) em `talk_assignments`; backfill: `week_start = date` retrocedido
  até a segunda-feira (`$date->startOfWeek(Carbon::MONDAY)`).
- **Dedupe antes do unique**: para semanas com mais de um slot `home|incoming` do mesmo time,
  manter o mais "avançado" (com speaker/status ≠ open; empate → mais recente) e apagar o resto.
- Unique parcial: (`team_id`, `week_start`) para `type in (home, incoming)`. `outgoing` fica fora
  do unique (podemos mandar orador para fora numa semana em que também recebemos/temos discurso? —
  sim, saída não conflita com o slot da casa).
- Atenção à regra `.ai/rules/app.md`: colunas `date` comparadas sempre com instância Carbon.

### 2. `ScheduleHorizon`

- Gerar por semana: para cada semana do horizonte, `firstOrCreate` por (`team_id`, `week_start`, tipo home/incoming),
  calculando `date` a partir do `meeting_weekday` da casa (default atual mantido se null).
- Se o `meeting_weekday`/`meeting_time` da casa mudar depois: slots `open` futuros têm a `date`
  **recalculada** (observer ou ação explícita no setup); slots já `scheduled|notified|confirmed`
  não mudam sozinhos — listar para o coordenador revisar.

### 3. Telas (mobile first, i18n em `lang/pt_BR/app/public_talks.php`)

- `Schedule.vue`: cartão por **semana** — título "Semana de :range" + linha com a reunião concreta
  ("Sáb., 15 de ago. · 18h"). Sem dois cartões na mesma semana.
- Saída (outgoing) e mesa de trabalho/Exchange: mostrar dia/horário **da parceira**; se ausente,
  CTA para completar o cadastro da congregação.
- Setup do módulo: `meeting_weekday`/`meeting_time` da casa passam a ser **obrigatórios** para
  ativar o módulo (hoje são nullable — manter nullable no banco do acervo, obrigatório só na casa).

### 4. Convite de troca

- `InviteComposer::week_line` e template/portal: incluir dia da semana + horário da nossa reunião.
  Ex. de linha: `15/08 (sáb.) às 18h — em aberto`.
- **Template Meta**: se o corpo do template WhatsApp mudar, ele precisa ser **resubmetido e
  aprovado** antes de uso em produção (regra da fase 3 — sandbox primeiro, nada vai à Meta sem
  aprovação do usuário).

### 5. Testes (Pest, mínimo necessário)

- Horizon: não duplica slot na mesma semana quando `meeting_weekday` muda; recalcula `date` de
  slots open; migration de dedupe (teste de migração ou teste do estado final).
- Composer/portal: linha da semana traz dia+horário.
- Outgoing usa dia/horário da parceira; bloqueio/aviso quando ausente.
- Lembrar regra `.ai/rules/tests.md` (UserFactory cria time pessoal) e rotas por `slug`.

## Critérios de aceite

- [ ] Nunca existem 2 cartões da mesma semana na tela (garantido por unique no banco).
- [ ] Cartão da semana mostra a data concreta derivada do dia/horário da reunião correta
      (casa para home/incoming; parceira para outgoing).
- [ ] Convite (WhatsApp/portal/copiado) mostra dia da semana + horário em cada semana ofertada.
- [ ] Mudar o `meeting_weekday` da casa não cria slots duplicados; slots open acompanham.
- [ ] Pint limpo; testes das áreas afetadas passando; zero texto fixo fora de `lang/`.

## Fora de escopo (não fazer agora)

- Reagendamento assistido de slots confirmados quando o horário muda.
- Semanas com dois discursos (assembleias/visitas) — não existe no produto.
