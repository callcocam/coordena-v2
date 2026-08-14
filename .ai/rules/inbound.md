---
paths:
  - 'app/Services/PublicTalks/Inbound/**'
---

# Inbound

## Inbound WhatsApp: só pela cadeia do InboundDispatcher
Todo inbound de WhatsApp do módulo de discursos passa por InboundDispatcher::HANDLERS (array único, ordem = precedência, testada como contrato em InboundDispatcherTest). Novo fluxo inbound = novo InboundHandler inserido na posição certa desse array — NUNCA um listener extra de WhatsAppMessageReceived (lição do v1: listeners concorrentes viraram bugs de ordem). matches() deve ser livre de efeitos; handle() é responsável por markForwarded()/markUnhandled(). Botões só roteiam por correlação de wamid (context_id), nunca pelo rótulo sozinho.
