---
paths:
  - 'app/Services/**'
---

# Services

## Model date casts return CarbonImmutable
AppServiceProvider chama Date::use(CarbonImmutable::class), então atributos de model com cast 'date'/'datetime' retornam Carbon\CarbonImmutable. Métodos de service que recebem datas vindas de models devem type-hintar Carbon\CarbonInterface (não Illuminate\Support\Carbon) e usar Carbon::instance($x) quando precisarem de instância mutável — senão TypeError em runtime.
