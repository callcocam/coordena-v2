---
paths:
  - 'tests/Feature/PublicTalks/**'
---

# Feature Public Talks

## Testes com D-1/D-3 do mesmo time: congelar o relógio
talk_assignments tem unique(team_id, week_start). Testes que criam assignments em Carbon::today()+1 e +3 para o MESMO time quebram quando os dois caem na mesma semana (ex.: rodando no domingo). Congele o relógio numa sexta (Carbon::setTestNow(Carbon::parse('next friday'))) como faz SpeakerRemindersCommandTest, ou use semanas explicitamente distintas.
