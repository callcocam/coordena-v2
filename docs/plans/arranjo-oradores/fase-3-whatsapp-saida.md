# Fase 3 — WhatsApp de saída: sandbox primeiro, Meta só com aprovação do usuário

Depende de: fase 2. Tamanho: M. **Leia antes: `00-INDICE.md`** e a infra WhatsApp Cloud já
existente no v2 (`app/` + `resources/js/pages/WhatsAppCloud/` — números, inbound, sandbox,
gestão de templates).

## Lição do v1 e a ordem correta

O v1 escreveu **12 templates e nunca submeteu nenhum à Meta**; o canal nunca operou.
A correção NÃO é submeter cedo às cegas — é ter um pipeline claro em 3 etapas:

1. **Sandbox**: escrever e testar os templates/fluxos inteiros no sandbox do v2, com
   mensagens reais de teste, até o fluxo estar redondo.
2. **Aprovação do usuário**: apresentar os textos finais (corpo, variáveis, botões,
   exemplos renderizados) e **aguardar o OK explícito**. **Nenhum template é submetido
   à Meta sem essa aprovação.**
3. **Meta**: só então submeter, acompanhar até aprovar e registrar nome/idioma/categoria.

A submissão à Meta portanto NÃO bloqueia o desenvolvimento: jobs, rotas e UI são
construídos e testados contra o sandbox; a troca para os templates aprovados é config.

## Escopo

Só **saída**. Toda resposta/entrada é fase 4. Máximo 4 templates:

| Template | Uso | Botões |
|---|---|---|
| `coordena_talk_assignment` | designação ao orador local (data, congregação, nº+título do esboço, link de referência) | quick replies `Tudo certo` / `Preciso remarcar` |
| `coordena_talk_reminder` | lembrete D-3 ao orador (disparo na fase 6; nasce aqui p/ testar junto) | mesmos |
| `coordena_exchange_invite` | convite de permuta à parceira: mês + **link do portal** (o conteúdo rico vai por sessão/portal — template é curto) | — |
| `coordena_coordinator_alert` | alerta genérico ao responsável/ajudantes fora da janela de 24h (corpo variável curto) | — |

Regras de template (herdadas do v1, fase 7): Meta rejeita `\n` em variável e corpo terminando
em variável → riqueza vai em **mensagem de sessão** (24h) ou no portal; template é só
abridor/notificador. Rótulos de botão são **exclusivos por fluxo** (roteamento por texto na
fase 4) — manter tabela de propriedade de rótulos num README junto às definições.
Escrever os textos já dentro dessas regras para não retrabalhar na etapa 3.

## Entregas

### 1. Templates no sandbox (primeiro)

- [ ] Definições no padrão de templates do v2 (ver `resources/js/pages/WhatsAppCloud/Templates`
  e o que existir em backend) + textos pt_BR profissionais, tom cordial.
- [ ] Testar cada template/fluxo no **sandbox** existente do v2 com envios reais de teste.
- [ ] Teste de definição (espelhar `TemplateDefinitionsTest` do v1): variáveis casam com o
  que os jobs enviam.

### 2. Envio de designação ao orador (contra o sandbox)

- [ ] Job `SendSpeakerAssignmentNotification` (`ShouldQueue`, tries 3, backoff 60, telefone
  normalizado): cria `talk_assignment_notification` (`kind=assignment`), grava `wamid`/`sent_at`,
  `failed()` marca `failed`; assignment `scheduled → notified`.
- [ ] Rota `POST .../schedule/{assignment}/notify` (policy `public-talks:notify`; valida slot
  `home` com orador+esboço e telefone). Reenvio permitido (nova linha).
- [ ] UI (fase 1): ativar o botão "Notificar" no bottom sheet + rodapé "Notificar pendentes";
  badge por notificação (`enviada|aguardando|confirmada|remarcar|falhou`); sem API configurada,
  fallback link `wa.me` com texto pronto.

### 3. Envio do convite de permuta (contra o sandbox)

- [ ] Na tela do convite (fase 2), canal `whatsapp` no envio: template `coordena_exchange_invite`
  ao contato da congregação + (se janela aberta) sessão com o texto rico do `InviteComposer`.
  Send fica `sent` com `wamid`; portal_token no link.
- [ ] Alerta `coordena_coordinator_alert` reutilizável: helper de envio a
  `ResponsibleCoordinator::recipientsFor()` (sessão se janela aberta, senão template).

### 4. Aprovação e submissão à Meta (por último, gate humano)

- [ ] Apresentar ao usuário os 4 textos finais com exemplos renderizados (variáveis
  preenchidas) e o mapa de botões. **Parar e aguardar aprovação explícita.**
- [ ] Só após o OK: submeter à Meta, acompanhar status, registrar nome/idioma/categoria
  aprovados e apontar a config de produção para eles.
- [ ] Se a Meta rejeitar algum: ajustar, **reapresentar ao usuário** e resubmeter.

## Testes

- [ ] Feature com fake do client Cloud API: envio grava wamid/status; bloqueios (sem telefone,
  slot vazio, sem permissão); reenvio cria nova linha; fallback manual sem API.
- [ ] Definições de template válidas (contagem/ordem de variáveis).

Rodar `vendor/bin/pint --dirty --format agent`.

## Critério de aceite

- Fluxos completos funcionando no **sandbox**: coordenador toca "Notificar" no celular →
  orador (número de teste) recebe o template com esboço e link; badge muda para "aguardando";
  envio de convite registra o send e entrega o link do portal.
- Textos dos 4 templates **aprovados pelo usuário** (registro do OK no PR/commit).
- Templates submetidos e aprovados na Meta **somente depois** desse OK, com nomes/categorias
  registrados.
