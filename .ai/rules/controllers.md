---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers em rotas com prefixo {current_team} precisam do parâmetro posicional
Rotas do grupo de time têm o prefixo {current_team}. Os parâmetros de rota são passados posicionalmente ao método do controller, então todo método de controller nessas rotas que receba um model binding DEVE declarar `string $current_team` antes do parâmetro do model (ex.: `update(FooRequest $request, string $current_team, Bar $bar)`). Omitir causa TypeError: a string do slug do time cai no lugar do model.
