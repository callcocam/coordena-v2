# Arquitetura de Traduções (Back + Front) — Coordena

Como as traduções são organizadas, alimentadas do backend para o frontend e
consumidas nos componentes Vue.

> **Regra do projeto (obrigatória):** nunca escreva texto fixo em Vue ou PHP.
> Todo texto visível ao usuário deve vir de uma chave de tradução PT-BR — via
> `useT()` no front ou `__()`/`trans()` no back. Isso vale para **tudo**: telas
> existentes já foram refatoradas e **toda nova tela/recurso já deve nascer com
> tradução** (criar a chave em `lang/pt_BR/...` antes/junto do componente).

---

## 1. Visão geral do fluxo

```
lang/pt_BR/**.php  ──(MergingFileLoader)──►  trans('grupo')
        │                                          │
        │                                  HandleInertiaRequests::share()
        │                                          │  prop 'translations'
        ▼                                          ▼
   PHP backend                              page.props.translations
   __('app.auth.login.title')                      │
                                              useT() → t('app.auth.login.title')
                                                     │
                                                 Componente Vue
```

A **mesma** árvore de traduções alimenta o backend (via `__()` / `trans()`) e o
frontend (via `useT()`), a partir dos arquivos em `lang/{locale}/`.

Locale padrão do projeto: **`pt_BR`** (`APP_LOCALE=pt_BR`, fallback `en`).

Não há `vue-i18n` no projeto — o front usa um composable próprio e leve
([`useT.ts`](../resources/js/composables/useT.ts)).

---

## 2. Backend — organização dos arquivos

### 2.1 Estrutura de pastas

```
lang/
├── pt_BR.json                    # traduções por string inteira (__('Texto…'))
└── pt_BR/
    ├── app.php                   # chaves "soltas" do grupo app (name, tagline)
    ├── app/                       # subdivisão do grupo "app" (1 arquivo por área)
    │   ├── common.php             #   → app.common.*     (botões/labels reusados)
    │   ├── nav.php                #   → app.nav.*         (sidebar, header, rodapé, menu do usuário)
    │   ├── auth.php               #   → app.auth.*        (login, cadastro, senha, 2FA)
    │   ├── marketing.php          #   → app.marketing.*   (Welcome / landing pública)
    │   ├── dashboard.php          #   → app.dashboard.*   (painel autenticado)
    │   ├── events.php             #   → app.events.*      (eventos: listagem/form)
    │   ├── volunteers.php         #   → app.volunteers.*  (voluntários + modais)
    │   ├── teams.php              #   → app.teams.*       (equipes, membros, papéis)
    │   ├── settings.php           #   → app.settings.*    (perfil, aparência, segurança)
    │   ├── components.php         #   → app.components.*  (modais/navegação compartilhados)
    │   └── logs.php               #   → app.logs.*        (logs do sistema)
    ├── auth.php                   # mensagens do framework (login falhou, throttle)
    ├── passwords.php              # reset de senha
    ├── pagination.php             # anterior/próximo
    └── validation.php             # mensagens + attributes traduzidos
```

> Conforme novas áreas surgirem, crie subpastas/arquivos seguindo o mesmo
> padrão — ex.: `lang/pt_BR/app/tenant/products.php` → `app.tenant.products.*`.
> O loader descobre arquivos e subpastas recursivamente.

### 2.2 Convenção de chaves

A chave reflete o **caminho do arquivo + as chaves internas**, com `.`:

| Arquivo                                          | Chave de acesso                |
| ------------------------------------------------ | ------------------------------ |
| `lang/pt_BR/app.php` → `'tagline'`               | `app.tagline`                  |
| `lang/pt_BR/app/auth.php` → `'login' => 'title'` | `app.auth.login.title`         |
| `lang/pt_BR/app/dashboard.php` → `'greeting'`    | `app.dashboard.greeting`       |

Padrão geral: `app.{área}.{recurso}.{campo}`.

### 2.3 O `MergingFileLoader` (peça-chave)

**Problema:** o Laravel nativo **não** mescla subpastas. `__('app.auth.login.title')`
(notação de ponto) lê apenas o arquivo plano `lang/pt_BR/app.php` — ele
desconhece `lang/pt_BR/app/auth.php` (que só seria acessível como
`__('app/auth.login.title')`, com barra). Dividir o grupo `app` em subpastas
quebraria todos os `__('app....')` do backend.

**Solução:** [`app/Support/Translation/MergingFileLoader.php`](../app/Support/Translation/MergingFileLoader.php)
estende o `FileLoader` do Laravel. Ao carregar um grupo (ex.: `app`), além do
arquivo `app.php` ele mescla **recursivamente** o diretório homônimo `app/` —
cada `.php` vira uma chave, cada subdiretório um nível aninhado.

Registro em [`AppServiceProvider::register()`](../app/Providers/AppServiceProvider.php)
(método `registerTranslationLoader()`) via `extend('translation.loader', ...)`,
preservando `paths()`, `jsonPaths()` e `namespaces()` do loader original (não
perde traduções do framework/pacotes nem o `pt_BR.json`).

Resultado: a notação de ponto funciona de forma transparente, **tanto no backend
quanto nas props do Inertia**:

```php
__('app.auth.login.title');                       // "Entre na sua conta"
__('app.dashboard.greeting', ['name' => 'Ana']);  // interpola :name (subpasta)
trans('app');                                      // árvore completa já mesclada
```

### 2.4 Mensagens por string inteira (`pt_BR.json`)

Mensagens curtas de backend (toasts flash, exceções, e-mails) usam `__()` com o
**texto** como chave, resolvidas por [`lang/pt_BR.json`](../lang/pt_BR.json):

```php
Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);
// pt_BR.json:  "Team created.": "Equipe criada."
```

Ao adicionar um novo toast/exceção com texto em inglês, adicione o par
correspondente ao `pt_BR.json`. (Strings já escritas diretamente em PT-BR
funcionam por fallback, mas o ideal é padronizar novas mensagens neste arquivo.)

---

## 3. Backend → Frontend — como é alimentado

As traduções são compartilhadas como **prop global do Inertia** em
[`HandleInertiaRequests::share()`](../app/Http/Middleware/HandleInertiaRequests.php):

```php
'translations' => fn (): array => [
    'app'        => trans('app'),
    'auth'       => trans('auth'),
    'passwords'  => trans('passwords'),
    'pagination' => trans('pagination'),
    'validation' => trans('validation'),
],
'locale' => app()->getLocale(),
```

- Cada `trans('grupo')` retorna a **árvore inteira** daquele grupo (já mesclada
  pelo `MergingFileLoader`).
- É uma **closure** (`fn () => ...`) → avaliada por requisição, de forma
  preguiçosa, no momento em que o Inertia monta a resposta.
- O resultado fica disponível no front em `page.props.translations`.

> **Adicionar um novo grupo ao front:** inclua uma linha `'grupo' => trans('grupo')`
> nessa lista. Sem isso, o grupo existe no backend mas não chega ao Vue.

---

## 4. Frontend — como é consumido

### 4.1 O composable `useT()`

[`resources/js/composables/useT.ts`](../resources/js/composables/useT.ts) lê
`page.props.translations` e navega pela chave dividindo por `.`:

```ts
const { t } = useT();
t('app.auth.login.title');                    // "Entre na sua conta"
t('app.dashboard.greeting', { name: 'Ana' }); // interpola :name
```

- Implementação própria, tipada (TS). Também expõe `locale`.
- Se a chave não existir, retorna a **própria chave** (fallback visível, útil
  para detectar tradução faltando direto na tela).
- Placeholders `:nome` são substituídos pelo segundo argumento.

### 4.2 Uso em componentes Vue

```vue
<script setup lang="ts">
import { useT } from '@/composables/useT';
const { t } = useT();
</script>

<template>
  <Head :title="t('app.auth.login.meta_title')" />
  <h1>{{ t('app.auth.login.title') }}</h1>
  <Input :placeholder="t('app.auth.fields.email_placeholder')" />
  <button :aria-label="t('app.nav.user.logout')">…</button>
</template>
```

**Regra do projeto (reforço):** nunca escrever texto fixo — sempre chave via
`t(...)`. Atributos como `title`, `placeholder`, `aria-label`, `alt` e `label`
também usam `:bind` com `t(...)`.

### 4.3 Títulos/descrições de layout via `defineOptions` (importante)

Páginas passam `title`/`description`/`breadcrumbs` ao layout persistente através
de `defineOptions({ layout: {...} })`. Esse objeto é **estático em tempo de
compilação** — **não** é possível chamar `t()` ali. A convenção do projeto é
passar a **chave de tradução** (string literal) e deixar o **componente de
layout** resolvê-la com `t()`:

```ts
// na página (compile-time — apenas a CHAVE):
defineOptions({
    layout: {
        title: 'app.auth.login.title',
        description: 'app.auth.login.description',
    },
});
```

Os componentes que renderizam esses valores já foram adaptados para resolver a
chave com `t()`:

- [`layouts/auth/AuthSimpleLayout.vue`](../resources/js/layouts/auth/AuthSimpleLayout.vue),
  `AuthCardLayout.vue`, `AuthSplitLayout.vue` → `{{ t(title) }}` / `{{ t(description) }}`
- [`components/Breadcrumbs.vue`](../resources/js/components/Breadcrumbs.vue) → `{{ t(item.title) }}`

Como `t()` devolve a própria chave quando não encontra, valores dinâmicos
(ex.: nome de um evento) passados nesses campos continuam funcionando.

Quando o título/descrição é **dinâmico em runtime**, use `setLayoutProps()`
dentro do `setup` (onde `t()` está disponível) — ver
[`auth/TwoFactorChallenge.vue`](../resources/js/pages/auth/TwoFactorChallenge.vue).

---

## 5. Como adicionar/dividir traduções

### Adicionar uma chave nova
1. Edite (ou crie) o arquivo apropriado em `lang/pt_BR/app/...`.
2. Use no back com `__('grupo.caminho.chave')` ou no front com `t('grupo.caminho.chave')`.
3. Nada mais a registrar — o loader descobre arquivos/subpastas por glob.

### Dividir um arquivo grande em subpasta
1. Crie `lang/pt_BR/{grupo}/{secao}.php` retornando um array.
2. Mova o bloco correspondente para lá (a chave passa a ser `grupo.secao.*`).
3. Garanta que o grupo está listado no `share()` (item 3) se precisar no front.
4. As chaves de ponto (`__('grupo.secao.x')`) continuam funcionando graças ao
   `MergingFileLoader`.

### Checklist ao criar uma tela/recurso novo
- [ ] Criar o arquivo de tradução (`lang/pt_BR/app/{área}.php`) com todas as
      strings da tela (títulos, labels, botões, placeholders, hints, meta_title).
- [ ] No Vue, importar `useT` e bindar **todo** texto com `t(...)`.
- [ ] `defineOptions({ layout: {...} })` recebe **chaves**, não texto.
- [ ] No back, usar `__()` para qualquer label resolvida server-side
      (enums, toasts, exceções). Toasts/exceções em inglês → mapear no `pt_BR.json`.
- [ ] Se for um grupo novo, adicioná-lo ao `share()`.

### Validar (via Docker)
```bash
# A chave resolve no backend (deve retornar o texto, não a chave)?
docker compose exec -T -e HOME=/tmp php php artisan tinker \
  --execute 'app()->setLocale("pt_BR"); echo __("app.auth.login.title");'

# Sintaxe dos arquivos de tradução:
docker compose exec -T -e HOME=/tmp php php -l lang/pt_BR/app/auth.php

# Front compila/tipa? (Node roda no host)
npm run types:check && npm run build
```
As traduções chegam ao browser por props em runtime — **não precisam de rebuild**.
Basta um hard refresh (Ctrl+Shift+R).

---

## 6. Arquivos-chave (referência rápida)

| Arquivo | Papel |
| --- | --- |
| `lang/pt_BR/**/*.php` | Fonte das traduções (arquivos planos + subpastas) |
| `lang/pt_BR.json` | Traduções por string inteira (toasts/exceções/e-mails) |
| [`app/Support/Translation/MergingFileLoader.php`](../app/Support/Translation/MergingFileLoader.php) | Mescla subpastas no grupo; faz a notação de ponto funcionar |
| [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) | Registra o loader via `extend('translation.loader')` |
| [`app/Http/Middleware/HandleInertiaRequests.php`](../app/Http/Middleware/HandleInertiaRequests.php) | Compartilha `translations` e `locale` como props Inertia |
| [`resources/js/composables/useT.ts`](../resources/js/composables/useT.ts) | Consome `page.props.translations` no front via `t()` |
