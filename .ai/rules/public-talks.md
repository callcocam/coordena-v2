---
paths:
  - 'app/Services/PublicTalks/**'
---

# Public Talks

## Congregações "unknown" ficam fora do rodízio de convites
ExchangeRoundRobin::candidatesFor só retorna congregações com exchange_opt = opted_in. Congregações com opt unknown precisam receber a apresentação (CongregationIntro) e dar opt-in antes de entrar no rodízio; elas aparecem via pendingIntroFor. Não reintroduza unknown em candidatesFor — o gate é intencional (Melhoria 2). O primeiro contato passa por SendCongregationIntro + IntroButtonHandler/ReactivationHandler no inbound.
