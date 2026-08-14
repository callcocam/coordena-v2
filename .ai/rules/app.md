---
paths:
  - 'app/**'
---

# App

## Comparar colunas com cast date usando Carbon, não toDateString()
Colunas com cast `date` (ex.: `exchange_invites.month`) são gravadas no SQLite como `Y-m-d H:i:s` (`2026-09-01 00:00:00`). Em `where`/`firstOrCreate`, passe a instância Carbon (`$month->startOfMonth()->startOfDay()`), nunca `->toDateString()` — a string `Y-m-d` não casa com o valor armazenado e `firstOrCreate` cria duplicata, estourando a unique de team_id+month.
