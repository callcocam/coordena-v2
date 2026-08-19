---
paths:
  - 'app/Http/Controllers/PublicTalks/**'
---

# Controllers Public Talks

## Envio manual de notificação é síncrono; agendado usa fila
Os botões manuais da programação (notify/notifyExchange) usam SendSpeakerAssignmentNotification::sendNowFor() (dispatchSync) para enviar na hora e reportar falha no toast; o comando public-talks:send-speaker-reminders segue usando queueFor() (fila). Nos testes das rotas manuais, use Bus::fake() + Bus::assertDispatchedSync() — Queue::fake() não intercepta dispatchSync e o job executaria de verdade.
