---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Páginas usam PageContainer (largura + voltar)
Toda página Inertia deve envolver o conteúdo em `<PageContainer>` (resources/js/components/PageContainer.vue) em vez de divs com padding manual. Props: `title`/`description` (renderiza Heading), `back-href` (botão de voltar com ArrowLeft, usar rota Wayfinder da listagem/página pai), `width` ('xl' | '3xl' | '5xl' | 'full', padrão '3xl' — usar '5xl' para páginas com tabelas/grades largas). Botões de ação vão no slot `#actions`. Exceções: Dashboard (grid full-width) e WhatsAppCloud/Sandbox (simulador full-screen). Páginas de settings/teams não precisam: o SettingsLayout já centraliza e limita a largura (max-w-5xl).

## PageContainer: cabeçalho custom via slot #heading
Se a página precisa de cabeçalho custom (badge ao lado do título, linhas de meta, etc.), NÃO coloque o header no slot default — o botão de voltar ficaria sozinho numa linha ("voando"). Use o slot `#heading` do PageContainer (renderiza ao lado da seta de voltar, dentro de um wrapper `min-w-0 flex-1`) e mova os botões para o slot `#actions`. Exemplo: publicTalks/congregations/Show.vue.
