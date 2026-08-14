<?php

namespace App\Services\PublicTalks;

use App\Enums\SpeakerNotificationKind;
use App\Models\TalkAssignment;
use App\Support\Phone;
use RuntimeException;

/**
 * Monta as variáveis do template de aviso/lembrete ao orador e o texto pronto
 * para o fallback `wa.me` (quando o time não tem API do WhatsApp configurada).
 *
 * O texto do fallback é o próprio corpo do template (lido do arquivo de
 * definição em `whatsapp-cloud.definitions_path`) com as variáveis aplicadas —
 * fonte única: o que a Meta aprovar é exatamente o que o wa.me pré-preenche.
 */
class TalkAssignmentMessage
{
    /**
     * The template variables keyed by the names in the template's `params`
     * config (same order as {{1}}..{{n}}).
     *
     * @return array<string, string>
     */
    public function params(TalkAssignment $assignment, SpeakerNotificationKind $kind): array
    {
        $speaker = $assignment->speaker;
        $outline = $assignment->outline;

        if ($speaker === null || $outline === null) {
            throw new RuntimeException('Assignment must have a speaker and an outline to be notified.');
        }

        $congregation = $assignment->team->homeCongregation;
        $congregationLine = $congregation?->name ?? $assignment->team->name;

        if ($congregation?->meeting_time !== null) {
            $congregationLine = __('app.public_talks.notifications.congregation_at_time', [
                'name' => $congregationLine,
                'time' => substr($congregation->meeting_time, 0, 5),
            ]);
        }

        $params = [
            'speaker' => $speaker->name,
            'date' => $assignment->date->translatedFormat('l, d/m'),
            'congregation' => $congregationLine,
            'outline' => __('app.public_talks.notifications.outline_line', [
                'number' => $outline->number,
                'title' => $outline->title,
            ]),
        ];

        if ($kind === SpeakerNotificationKind::Assignment) {
            $params['reference'] = $outline->reference_url
                ?? __('app.public_talks.notifications.reference_fallback');
        }

        // Meta rejeita quebra de linha dentro de variável.
        return array_map(
            fn (string $param): string => trim(preg_replace('/\s+/u', ' ', $param) ?? ''),
            $params,
        );
    }

    /**
     * The template body with the variables applied (wa.me fallback / preview).
     */
    public function text(TalkAssignment $assignment, SpeakerNotificationKind $kind): string
    {
        $body = $this->templateBody($this->templateName($kind));

        foreach (array_values($this->params($assignment, $kind)) as $index => $param) {
            $body = str_replace('{{'.($index + 1).'}}', $param, $body);
        }

        return $body;
    }

    /**
     * The wa.me link with the message pre-filled, or null without a valid phone.
     */
    public function waMeUrl(TalkAssignment $assignment, SpeakerNotificationKind $kind): ?string
    {
        $phone = Phone::normalize($assignment->speaker?->phone);

        if ($phone === null) {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($this->text($assignment, $kind));
    }

    /**
     * The approved template name configured for this notification kind.
     */
    public function templateName(SpeakerNotificationKind $kind): string
    {
        $name = config('whatsapp-cloud.templates.'.$kind->templateKey().'.name');

        if (! is_string($name) || $name === '') {
            throw new RuntimeException("Template for kind [{$kind->value}] is not configured.");
        }

        return $name;
    }

    /**
     * The BODY text of a template definition file.
     */
    protected function templateBody(string $templateName): string
    {
        $path = rtrim((string) config('whatsapp-cloud.definitions_path'), '/')."/{$templateName}.php";

        if (! is_file($path)) {
            throw new RuntimeException("Template definition [{$templateName}] not found at [{$path}].");
        }

        /** @var array{components: list<array{type: string, text?: string}>} $definition */
        $definition = require $path;

        foreach ($definition['components'] as $component) {
            if ($component['type'] === 'BODY') {
                return $component['text'] ?? '';
            }
        }

        throw new RuntimeException("Template definition [{$templateName}] has no BODY component.");
    }
}
