---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Não criar método queue() em jobs
O Illuminate\Bus\Dispatcher chama $command->queue($queue, $command) quando o job define um método chamado `queue`. Um helper estático `queue()` colide com esse hook e quebra o dispatch (recebe QueueFake/Queue como argumento). Use outro nome, ex.: `queueFor()`. Rotas com {current_team} resolvem por slug — em testes, passe $team->slug (ou o model), nunca $team->id.
