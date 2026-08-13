<?php

namespace App\Support;

/**
 * Normalização de telefones para o formato E.164 sem o "+" (só dígitos).
 *
 * Todo telefone gravado no módulo de discursos passa por aqui: aceita máscara
 * brasileira ("(51) 99999-0000"), prefixo internacional ("+55 ...") e zeros de
 * operadora, e devolve os dígitos com DDI (default 55 quando ausente).
 */
class Phone
{
    protected const DEFAULT_COUNTRY_CODE = '55';

    /**
     * Normalize a phone number to digits with country code, or null when empty/invalid.
     */
    public static function normalize(?string $phone, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Zeros de operadora/discagem à esquerda ("0 51 ..." → "51 ...").
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        // Já veio com DDI (ex.: "+55 51 99999-0000" → 12–13 dígitos).
        if (str_starts_with($digits, $countryCode) && strlen($digits) >= 12) {
            return $digits;
        }

        // DDD + número (10–11 dígitos): completa com o DDI default.
        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return $countryCode.$digits;
        }

        // Curto demais para ser um telefone completo.
        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }
}
