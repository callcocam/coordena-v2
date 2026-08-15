# Melhoria 7 — Portal: oradores já cadastrados da parceira + tags de temas profissional (mobile-first)

> Doc autossuficiente para ser executado por um **chat separado**, DEPOIS da melhoria 4
> (portal bidirecional). Antes de codar: leia `00-INDICE.md`, `melhoria-4-portal-bidirecional.md`
> e as regras em `.ai/rules/` (rode `grep -rin 'keyword' .ai/rules`, em especial `pages.md`).
> Terminologia: **troca(s)**, nunca "permuta".

## Contexto e decisões do dono (2026-08-14)

Todo o trato é **com UMA congregação** — a parceira está cadastrada e relacionada ao convite, e
seus oradores vão sendo cadastrados no sistema a cada troca (o portal já faz
`Speaker::firstOrCreate` na congregação parceira ao submeter). Hoje, porém, o portal trata o
campo "Nome do orador" como texto livre sempre, e os esboços como números digitados do zero.

Decisões:

1. **Listar os oradores já cadastrados** da congregação parceira no portal, para o coordenador
   convidado **selecionar** em vez de digitar.
2. Se o orador não existir: **cadastro silencioso** — ele simplesmente digita o nome novo no
   mesmo campo (combobox com "creatable"); NADA de modal/botão "cadastrar novo". O
   `firstOrCreate` no submit já materializa.
3. Ao **selecionar** um orador cadastrado: **pré-preencher** telefone e os **temas dele**
   (`speaker->outlines`) como chips, com liberdade de **remover ou adicionar** temas.
4. Seleção de temas **profissional**: base no shadcn-vue Tags Input
   (`resources/js/components/ui/tags-input`, já instalado) combinado com busca — o usuário
   pesquisa por **número OU título** do esboço no catálogo completo.
5. **Catálogo completo de temas carregado no frontend** de uma vez (props do portal; a fonte é
   `PublicTalkOutline` — semeado de `database/data/public_talk_outlines.php`). Depois só
   alimenta os campos client-side; sem requests de busca.
6. Layout: o campo de temas ocupa **uma linha inteira** do cartão da semana. Chips exibidos com
   **limite** (ex.: 6) e um chip "+20" que expande (popover/sheet) para ver/editar a lista
   completa.
7. Se o Tags Input do shadcn não der conta, **criar um componente próprio reutilizável** — ele
   tende a ser usado em outros lugares (cadastro de oradores em `congregations/Show.vue`,
   mesa da melhoria 5). Já existe `resources/js/components/OutlinePicker.vue` (single/multiple,
   busca, usado em `Schedule.vue` e `congregations/Show.vue`): **avaliar primeiro evoluir o
   OutlinePicker** para o modo tags/chips em vez de criar um terceiro componente concorrente.
8. **Mobile-first é obrigatório**: o portal é usado quase sempre no celular. Alvos de toque
   generosos, chips que quebram linha, busca em `Sheet`/drawer no mobile se popover ficar
   apertado, teclado numérico quando pesquisar por número.

## Fases

### Fase A — Backend: props do portal

- `ExchangePortalController::show` passa a enviar:
  - `partnerSpeakers`: oradores ativos da congregação parceira do send —
    `{id, name, phone, outline_ids[]}` (relação `speaker->outlines`, só ids + o catálogo cobre
    o resto). Não vazar oradores de outras congregações.
  - `outlineCatalog`: catálogo completo `{id, number, title}` ordenado por número (payload
    pequeno; uma vez só).
- `SubmitExchangePortalRequest` / submit:
  - `incoming[]` aceita `speaker_id` (da parceira — validar pertencimento) OU
    `speaker_name` novo (fluxo atual). Com `speaker_id`, telefone atualiza se enviado.
  - Esboços passam a chegar como `outline_ids[]` (ids do catálogo) em vez de números soltos;
    manter validação de existência.

### Fase B — Componente de temas (tags + busca)

- Evoluir `OutlinePicker.vue` (preferencial) ou criar `OutlineTagsInput.vue` em
  `resources/js/components/`:
  - modelValue: `outline_ids[]`;
  - render: chips `número — título` truncado, botão × por chip;
  - input de busca inline (filtra por número e por título, acento-insensível);
  - colapso: mostra até N chips + chip "+{restante}" que abre a lista completa
    (Popover no desktop, Sheet no mobile);
  - teclável e acessível; funciona com dedo (não depender de hover).
- Prop `catalog` recebida de fora (o portal injeta `outlineCatalog`) — o componente não busca
  dados sozinho, para ser reutilizável em telas autenticadas também.

### Fase C — Portal: cartão da semana

- Campo "Nome do orador" vira **combobox creatable**: lista `partnerSpeakers` com busca; opção
  "Usar \"{texto}\"" quando não há match (cadastro silencioso).
- Ao selecionar orador cadastrado: preencher telefone + chips de temas com `outline_ids` dele;
  troca de orador re-preenche (com confirmação leve se o usuário já mexeu nos chips).
- Campo de temas em **linha inteira** usando o componente da Fase B.
- Seção 2 (escolher orador NOSSO): reaproveitar o mesmo padrão visual para o select de tema do
  nosso orador (catálogo filtrado pelos `outlines` dele) — sem tags (é escolha única), mas com
  a mesma busca número/título.

## Testes (Pest, obrigatórios)

- `show` expõe `partnerSpeakers` só da congregação parceira + `outlineCatalog` completo;
- submit `incoming` com `speaker_id` da parceira → reusa o Speaker (não duplica) e sincroniza
  os `outline_ids` na oferta;
- submit com `speaker_id` de OUTRA congregação → erro de validação;
- submit com nome novo → cria Speaker silenciosamente na parceira (comportamento atual mantido);
- `outline_ids` inexistentes → erro de validação.
- Rodar suite PublicTalks afetada + `vendor/bin/pint --dirty --format agent`.

## Fora de escopo

- Mesa de montagem (melhoria 5).
- Edição de oradores da parceira pelo portal além de telefone/temas da oferta.
