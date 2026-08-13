---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders rodam sem model events
DatabaseSeeder usa WithoutModelEvents, então hooks de creating/created não disparam em seeders chamados por ele (ex.: geração de slug do Team). Defina esses campos explicitamente (ex.: Str::slug) ao criar modelos em seeders. Seeders devem ser idempotentes: rodar 2× não pode duplicar (use updateOrCreate/guards por chave natural).
