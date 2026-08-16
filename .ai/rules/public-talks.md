---
paths:
  - 'app/Services/PublicTalks/**'
---

# Public Talks

## Congregações "unknown" ficam fora do rodízio de convites
ExchangeRoundRobin::candidatesFor só retorna congregações com exchange_opt = opted_in. Congregações com opt unknown precisam receber a apresentação (CongregationIntro) e dar opt-in antes de entrar no rodízio; elas aparecem via pendingIntroFor. Não reintroduza unknown em candidatesFor — o gate é intencional (Melhoria 2). O primeiro contato passa por SendCongregationIntro + IntroButtonHandler/ReactivationHandler no inbound.

## Template de orador varia pela direção do assignment
A chave do template WhatsApp do orador NÃO vem mais de SpeakerNotificationKind::templateKey() (removido). Use TalkAssignmentMessage::templateKey($assignment, $kind): Assignment → talk_assignment (home/outgoing) ou talk_assignment_visitor (incoming); Reminder → talk_reminder (home), talk_reminder_out (outgoing), talk_reminder_visitor (incoming). Incoming é notificável: o visitante recebe aviso/lembrete direto de nós. Novo template exige entrada em config/whatsapp-cloud.php + arquivo em database/whatsapp-templates + linha na tabela de rótulos do README (TemplateDefinitionsTest guarda a sincronia).

## Prazos do módulo via PublicTalkSettings, não config() direto
Os prazos de lembrete/alerta/reengate/expiração têm override por time na coluna jsonb teams.public_talk_settings, resolvidos por App\Services\PublicTalks\PublicTalkSettings (->for($team)->get('chave')); config/public_talks.php é só o default global. Código novo (comandos, jobs, controllers) NÃO deve ler config('public_talks.reminders...')/config('public_talks.exchange...') direto, senão ignora a personalização do time. save() descarta overrides iguais ao default — teste de override deve usar valor diferente do global.
