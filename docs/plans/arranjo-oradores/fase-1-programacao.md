# Fase 1 — Programação local (tela-mãe) + acervo + setup do módulo

Depende de: fase 0 completa. Tamanho: G. **Leia antes: `00-INDICE.md`** (produto, regras
invioláveis e modelo de dados). Sem WhatsApp nesta fase.

## Objetivo

O coordenador de discursos administra a programação da casa pelo painel, **no celular**:
vê os meses do horizonte, preenche orador/esboço das semanas da casa e gerencia o acervo
de congregações/oradores. Módulo bloqueado até o setup mínimo existir.

## Lição do v1 a corrigir aqui

A lista de semanas tinha **informação demais**. Regra desta fase: o cartão da semana mostra
no máximo 3 linhas (data+hora, orador+esboço, 1 badge de status). Todo o resto vive no
bottom sheet/painel de detalhe.

## UX (mobile first — validado com o usuário)

```
┌──────────────────────────────┐
│  ◀  Novembro 2026  ▶         │  ← navegação por mês (swipe/setas)
├──────────────────────────────┤
│ ⚠ 2 discursos sem confirmação│  ← faixa apenas quando há pendência
├──────────────────────────────┤
│ Sáb 07/11 · 19:30            │
│ João Silva · Esboço 12       │
│ ● Confirmado                 │
├──────────────────────────────┤
│ Sáb 21/11 · 19:30            │
│ — sem orador —               │
│ ○ Em aberto                  │
└──────────────────────────────┘
│ [ Notificar pendentes (2) ]  │  ← rodapé fixo, só quando útil (ação real na fase 3;
└──────────────────────────────┘     aqui renderiza desabilitado/oculto)
```

- Tocar no cartão → **bottom sheet** (não modal empilhada): editar orador (busca no acervo da
  casa, com indicador de disponibilidade de `SpeakerAvailability`), editar esboço (busca por
  número/título no catálogo), status, futuro botão de notificação.
- Semana de permuta (incoming/outgoing) aparece na mesma lista com rótulo da congregação
  contraparte; edição dela é da fase 2 — aqui é somente leitura.
- Desktop/tablet: mesmo layout empilhado ganha largura máxima e o bottom sheet vira painel
  lateral. O empilhado é a base; duas colunas é enhancement.

## Entregas

### 1. Setup/gate do módulo

- [ ] Página do módulo (`/{team}/discursos`) com gate em cascata: sem `home_congregation_id`
  → tela de setup pedindo para escolher/criar a congregação-casa; sem coordenador `responsible`
  ativo → tela de setup do responsável (+ ajudantes opcionais). Só então a programação abre.
- [ ] Ao completar o setup, chamar `ScheduleHorizon::ensure()` (o horizonte também será
  garantido a cada visita à programação até a fase 6 automatizar).

### 2. Programação

- [ ] Controller + rotas nomeadas (Wayfinder para o frontend), policies `public-talks:*`.
- [ ] Página Inertia `resources/js/pages/PublicTalks/Schedule.vue`: meses do horizonte,
  cartões de semana, bottom sheet de edição.
- [ ] Edição do slot `home`: orador (obrigatório do acervo da casa — FK; sem digitação livre)
  + esboço; grava `status = scheduled`; limpar volta a `open`. `created_by_id` preenchido.
- [ ] Faixa de pendências do mês corrente (contagem de `scheduled` não confirmados).

### 3. Acervo (congregações + oradores)

- [ ] CRUD de congregações (RBAC `congregations:view|manage`): lista com busca por
  nome/cidade/circuito, form com contatos/secretário, `meeting_weekday/time`, `exchange_opt`.
- [ ] Oradores dentro da congregação: CRUD + gestão dos esboços preparados
  (`speaker_outlines`, seletor múltiplo com busca no catálogo).
- [ ] Oradores da **casa** também são geridos aqui (mesma tela — casa é uma congregação do acervo).
- [ ] Tela de coordenador responsável/ajudantes do time (usada pelo setup): impedir remover
  o responsável sem substituto.

### 4. i18n e tipos

- [ ] Textos em `lang/pt_BR/` (seguir o padrão de tradução do projeto — ver
  `docs/traducoes-arquitetura.md` se aplicável).
- [ ] Tipos TS dos payloads em `resources/js/types/`.

## Testes

- [ ] Feature: gate do módulo (sem casa → setup; sem responsável → setup; completo → programação).
- [ ] Feature: edição de slot exige orador do acervo da casa (rejeita orador de outra
  congregação/dono); `SpeakerAvailability` refletida no payload.
- [ ] Feature: CRUD do acervo respeita `congregations:*`; membro sem permissão não vê acervo,
  mas usa a programação normalmente.
- [ ] Browser (Pest v4, viewport mobile): abrir programação, tocar semana, escolher orador e
  esboço pelo bottom sheet, ver badge atualizar. Smoke desktop também.

Rodar `vendor/bin/pint --dirty --format agent`; `npm run build` ao final.

## Critério de aceite

- No celular: setup guiado → programação por mês com cartões de 3 linhas → preencher uma
  semana em ≤ 4 toques (cartão → orador → esboço → salvar).
- Semana só aceita orador cadastrado; disponibilidade visível na escolha.
