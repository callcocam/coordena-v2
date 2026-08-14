<?php

/**
 * Garante que cada template configurado em `whatsapp-cloud.templates` tem um
 * arquivo de definição válido em `definitions_path` — o require já dispara as
 * validações do TemplateBuilder (corpo não pode começar/terminar em variável,
 * exemplos 1:1, sem quebra de linha em variável).
 */
test('every configured template has a matching definition file', function () {
    $path = config('whatsapp-cloud.definitions_path');

    foreach (config('whatsapp-cloud.templates') as $key => $template) {
        $file = $path.'/'.$template['name'].'.php';

        expect(file_exists($file))->toBeTrue("Template '{$key}' has no definition file at {$file}.");
    }
});

test('definition files match the configured name, language and param count', function () {
    $path = config('whatsapp-cloud.definitions_path');

    foreach (config('whatsapp-cloud.templates') as $key => $template) {
        $payload = require $path.'/'.$template['name'].'.php';

        expect($payload)->toBeArray()
            ->and($payload['name'])->toBe($template['name'])
            ->and(strtolower($payload['language']))->toBe(strtolower($template['language']));

        $body = collect($payload['components'])->firstWhere('type', 'BODY');

        expect($body)->not->toBeNull("Template '{$key}' has no BODY component.");

        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body['text'], $matches);
        $maxIndex = $matches[1] === [] ? 0 : max(array_map('intval', $matches[1]));

        expect($maxIndex)->toBe(
            count($template['params']),
            "Template '{$key}' body uses {$maxIndex} variable(s) but config declares "
                .count($template['params']).' param(s).',
        );
    }
});
