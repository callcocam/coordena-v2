---
paths:
  - 'tests/**'
---

# Tests

## UserFactory sempre cria time pessoal
User::factory()->create() cria um time pessoal, dá cargo Coordenador e define current_team_id (via configure/afterCreating). Em testes que precisam de usuário "sem time atual", zere explicitamente: $user->forceFill(['current_team_id' => null])->save().
