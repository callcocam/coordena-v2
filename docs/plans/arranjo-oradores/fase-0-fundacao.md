# Fase 0 — Fundação: dados, models e serviços puros

Depende de: nada. Tamanho: M/G. **Leia antes: `00-INDICE.md`** (modelo de dados é a seção
"Modelo de dados" de lá — este arquivo não o repete; qualquer divergência, o índice manda).
Sem WhatsApp e sem telas nesta fase (exceto nenhuma — é backend puro + seeders).

## Objetivo

Deixar o domínio inteiro de pé, testado e semeado, sem nenhuma UI: quem terminar esta fase
entrega models navegáveis via tinker/testes e serviços de regra de negócio prontos para as
fases 1 e 2 consumirem.

## Contexto do projeto

- Laravel + Inertia v3/Vue, times com RBAC próprio (`app/Support/PermissionCatalog.php`,
  `Role`/`Permission`/`Membership`). WhatsApp Cloud já tem tabelas próprias (`whatsapp_numbers`,
  `whatsapp_inbound_messages`) — não tocar nesta fase.
- Dados a portar do projeto v1 (`~/projects/coordena`):
  - `database/data/public_talk_outlines.php` — catálogo dos 194 esboços (copiar).
  - `database/data/congregations.local.php` — congregações REAIS do circuito, **fora do
    versionamento** (copiar o arquivo e a entrada no `.gitignore`).
  - Estrutura dos seeders `PublicTalkOutlineSeeder`, `CongregationSeeder`, `PublicTalkDemoSeeder`
    como referência de forma (idempotência via `firstOrNew`/`updateOrCreate`) — **adaptar** ao
    modelo do v2 (acervo por `owner_user_id`, não por team).

## Entregas

### 1. Migrations + models + enums

Criar exatamente o modelo do `00-INDICE.md` §"Modelo de dados", com `php artisan make:model -mf`:

- [ ] Acervo: `Congregation`, `Speaker`, `speaker_outlines` (pivot). Enums:
  `ExchangeOpt` (`OptedIn|OptedOut|Unknown`), `SpeakerRole` (`Elder|MinisterialServant|Other`).
- [ ] Catálogo: `PublicTalkOutline` + enum `PublicTalkOutlineStatus`.
- [ ] Time: `teams.home_congregation_id` (migration de alteração), `Coordinator` + enum
  `CoordinatorRole` (`Responsible|Helper`), `TalkAssignment` + enums `TalkAssignmentType`
  (`Home|Incoming|Outgoing`) e `TalkAssignmentStatus`
  (`Open|Scheduled|Notified|Confirmed|NeedsReschedule`), `TalkAssignmentNotification`.
- [ ] Permuta (só migrations/models; comportamento é fase 2): `ExchangeInvite`,
  `ExchangeInviteSend`, `ExchangeMessage`, `ExchangeOffer`, `exchange_offer_outlines` + enums
  `ExchangeInviteStatus`, `ExchangeInviteSendStatus`, `ExchangeOfferStatus`.
- [ ] Casts/relations completos nos dois sentidos; telefone sempre gravado normalizado
  (criar `App\Support\Phone::normalize()` com testes — dígitos + DDI 55 default, tolerante a máscara).
- [ ] Scoping: models do time usam o padrão de team scope existente no projeto; models do acervo
  ganham scope por `owner_user_id` (helper para resolver o dono a partir do time atual:
  `Team->owner`). Catálogo de esboços sem scope.

### 2. Autorização

- [ ] Adicionar ao `PermissionCatalog`: `congregations:view`, `congregations:manage`,
  `public-talks:view`, `public-talks:manage` (programação, permutas e mesa de trabalho),
  `public-talks:notify` (disparos WhatsApp — usada a partir da fase 3).
- [ ] Policies: `CongregationPolicy`, `SpeakerPolicy` (acervo — mas ver regra abaixo),
  `TalkAssignmentPolicy`, `ExchangeInvitePolicy`, `CoordinatorPolicy`.
- [ ] **Regra especial (decisão de produto)**: criar/editar `Speaker` NÃO exige
  `congregations:manage` quando feito dentro do módulo de discursos (mesa de trabalho/programação)
  — `public-talks:manage` basta, e o escopo da congregação vem do contexto (convite/casa),
  nunca de input livre. `congregations:manage` é só para o CRUD do acervo em si.

### 3. Serviços puros (regras de negócio, sem I/O)

- [ ] `App\Services\PublicTalks\ScheduleHorizon`:
  - `ensure(Team $team): void` — garante `talk_assignments` tipo `home` (status `open`) para
    todos os fins de semana dos **próximos 3 meses** a partir do mês corrente; quando restam
    só 2 meses com semanas geradas, cria o mês seguinte. Idempotente (unique team+date+type).
  - Fim de semana = dia derivado de `Congregation.meeting_weekday` da casa (default sábado).
- [ ] `App\Services\PublicTalks\SpeakerAvailability`:
  - `canGoOut(Speaker $s, CarbonInterface $month): bool` — false se o orador já tem
    `talk_assignment` `outgoing`/`incoming` confirmado OU `exchange_offer` `selected|confirmed`
    naquele mês, **em qualquer time do mesmo dono** (a checagem roda sem team scope, filtrando
    pelo acervo do dono).
  - `availableFor(Congregation $c, CarbonInterface $month): Collection` — oradores ativos da
    congregação que passam em `canGoOut`, com seus esboços preparados.
- [ ] `App\Services\PublicTalks\ResponsibleCoordinator`:
  - `for(Team $team): ?Coordinator` (responsável ativo) e `recipientsFor(Team $team): Collection`
    (responsável + ajudantes ativos com telefone).

### 4. Seeders

- [ ] `PublicTalkOutlineSeeder` — portar do v1 (idempotente por `number`, preserva títulos manuais).
- [ ] `CongregationSeeder` — adaptar: semeia o acervo para um **usuário dono** (parâmetro/first user),
  a partir de `congregations.local.php` quando existir; senão dados fake em não-produção.
- [ ] `PublicTalkDemoSeeder` — oradores por congregação + esboços por orador + coordenador
  responsável/ajudante do time demo (não roda em produção).
- [ ] Registrar no `DatabaseSeeder` seguindo o padrão do projeto.

## Testes (Pest, feature na maioria)

- [ ] `PhoneTest` (unit): normalização.
- [ ] `ScheduleHorizonTest`: gera 3 meses; rodar 2× não duplica; avança quando restam 2 meses;
  respeita `meeting_weekday`.
- [ ] `SpeakerAvailabilityTest`: bloqueio por compromisso no mês; visibilidade entre times do
  mesmo dono; oferta `selected` também bloqueia.
- [ ] `ResponsibleCoordinatorTest`: resolução responsável/ajudantes; um único responsável ativo.
- [ ] `AcervoScopeTest`: time A e time B do mesmo dono veem as mesmas congregações; dono
  diferente não vê; permissões `congregations:*` respeitadas.
- [ ] Seeders rodam 2× sem duplicar (teste smoke).

Rodar `vendor/bin/pint --dirty --format agent` ao final.

## Critério de aceite

- `php artisan migrate:fresh --seed` deixa o banco com catálogo de 194 esboços, congregações,
  oradores com temas, time demo com casa e coordenador configurados.
- Todos os testes acima verdes; nenhum controller/tela criada.
