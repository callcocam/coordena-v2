# Templates WhatsApp — discursos públicos

Cada arquivo `*.php` desta pasta retorna o payload de criação de um template
(via `TemplateBuilder::...->toArray()`), lido por `whatsapp:template:create
<nome>` e pelo sandbox (renderização do corpo). O **mesmo** template também
precisa constar em `config('whatsapp-cloud.templates')` com os `params` na
ordem exata dos `{{n}}` — os dois lugares são sincronizados à mão e ordem
divergente falha silenciosamente (`tests/Feature/Whatsapp/TemplateDefinitionsTest.php`
guarda essa sincronia).

Regras duras da Meta (rejeição código 100):

- Sem `\n` dentro de variável; o valor de cada variável é uma linha só.
- O corpo nunca começa nem termina em variável.
- Exemplo por variável obrigatório, sem quebras de linha.
- Conteúdo rico (listas, parágrafos) vai por mensagem de sessão ou portal.

## Propriedade dos rótulos de quick reply

Rótulos são **exclusivos por fluxo**: a fase 4 roteia a resposta pelo texto do
botão, então reaproveitar um rótulo faria a resposta cair no handler errado.
Antes de criar um botão novo, confira esta tabela e registre o dono aqui.

| Rótulo | Dono (fluxo) | Templates |
| --- | --- | --- |
| `Tudo certo` | Confirmação do orador | `coordena_talk_assignment`, `coordena_talk_reminder` |
| `Preciso remarcar` | Confirmação do orador | `coordena_talk_assignment`, `coordena_talk_reminder` |
| `Sim, vamos combinar` | Convite de troca (parceira) | `coordena_exchange_invite` |
| `Podemos ajudar` | Convite de troca (parceira) | `coordena_exchange_help` |
| `Este mês não` | Convite de troca (parceira) | `coordena_exchange_invite`, `coordena_exchange_help` |

Os botões do convite roteiam por correlação de `wamid` (nunca só pelo rótulo):
o aceite abre a janela de 24h e o conteúdo rico (semanas, oradores, link do
portal `portal_token`) segue por mensagem de sessão gratuita.
`coordena_coordinator_alert` não tem botões de propósito: o alerta só aponta
para a tela.

## Estado na Meta

Nenhum template foi submetido ainda. O pipeline da fase 3 é: sandbox →
aprovação explícita do usuário dos textos finais → submissão à Meta. Registrar
aqui nome/idioma/categoria e status quando houver submissão.
