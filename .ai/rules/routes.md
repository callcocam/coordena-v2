---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Regenerar Wayfinder sempre com --with-form
O vite.config.ts usa wayfinder({ formVariants: true }). Ao regenerar manualmente, rode `php artisan wayfinder:generate --with-form`; sem a flag, as variantes `.form` somem e o types:check quebra em ~15 arquivos (auth, settings, teams).
