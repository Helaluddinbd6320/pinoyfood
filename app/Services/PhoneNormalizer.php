<?php

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Turns any typed phone number into three values (spec section 5):
 *   raw    exactly as typed (trimmed, max 40 characters)
 *   e164   clean "+9665XXXXXXXX" form, or null if we cannot make sense of it
 *   search last 9 digits, used as a fallback for matching
 *
 * It never throws and never rejects a number. An unusable number simply
 * comes back with e164 = null so the admin can see a "check this number" badge.
 */
class PhoneNormalizer
{
    private const RAW_MAX = 40;

    private const SEARCH_LENGTH = 9;

    // Zero-width marks, LRM/RLM, bidi embedding and isolate marks, BOM.
    private const INVISIBLE = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u';

    private const SEPARATORS = '/[\s\x{00A0}\-.()\[\]\/_]/u';

    /** @var array<string, string>|null */
    private static ?array $digitMap = null;

    private PhoneNumberUtil $util;

    public function __construct()
    {
        $this->util = PhoneNumberUtil::getInstance();
    }

    /**
     * @return array{raw: ?string, e164: ?string, search: ?string}
     */
    public function normalize(?string $input): array
    {
        $text = trim((string) $input);

        if ($text === '') {
            return $this->result(null, null, null);
        }

        $raw = mb_substr($text, 0, self::RAW_MAX);

        // 1. Arabic-Indic and Persian digits become ASCII digits.
        $text = strtr($text, self::digitMap());

        // 2. Remove invisible marks, then spaces, dashes, dots and brackets.
        $text = preg_replace(self::INVISIBLE, '', $text) ?? '';
        $compact = preg_replace(self::SEPARATORS, '', $text) ?? '';

        $hasPlus = str_starts_with($compact, '+');
        $digits = preg_replace('/\D/', '', $compact) ?? '';

        if ($digits === '') {
            return $this->result($raw, null, null);
        }

        $e164 = $this->toE164($digits, $hasPlus);

        // 8. Last 9 digits. Prefer the clean E.164 digits when we have them.
        $source = $e164 !== null ? substr($e164, 1) : $digits;
        $search = substr($source, -self::SEARCH_LENGTH);

        return $this->result($raw, $e164, $search);
    }

    /**
     * WhatsApp links use E.164 digits without the plus (spec section 5).
     * Example: "+966501234567" becomes "966501234567".
     */
    public function whatsappDigits(?string $e164): ?string
    {
        return $e164 === null ? null : ltrim($e164, '+');
    }

    private function toE164(string $digits, bool $hasPlus): ?string
    {
        // 3. A leading 00 means the same as +.
        if (! $hasPlus && str_starts_with($digits, '00')) {
            $hasPlus = true;
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        // 4. libphonenumber: default region SA, then retry with PH.
        if ($hasPlus) {
            $e164 = $this->parse('+'.$digits, null);
        } else {
            $e164 = $this->parse($digits, 'SA') ?? $this->parse($digits, 'PH');
        }

        // 5 and 6. Saudi and Philippine mobile fallbacks.
        return $e164 ?? $this->fallback($digits, $hasPlus);
    }

    private function parse(string $number, ?string $region): ?string
    {
        try {
            $parsed = $this->util->parse($number, $region);

            return $this->util->isValidNumber($parsed)
                ? $this->util->format($parsed, PhoneNumberFormat::E164)
                : null;
        } catch (NumberParseException) {
            return null;
        }
    }

    private function fallback(string $digits, bool $hasPlus): ?string
    {
        // With a plus sign the country code must be explicit.
        $saudi = $hasPlus ? '/^966(5\d{8})$/' : '/^(?:966|0)?(5\d{8})$/';
        $philippines = $hasPlus ? '/^63(9\d{9})$/' : '/^(?:63|0)?(9\d{9})$/';

        if (preg_match($saudi, $digits, $m)) {
            return '+966'.$m[1];
        }

        if (preg_match($philippines, $digits, $m)) {
            return '+63'.$m[1];
        }

        return null;
    }

    /**
     * @return array{raw: ?string, e164: ?string, search: ?string}
     */
    private function result(?string $raw, ?string $e164, ?string $search): array
    {
        return ['raw' => $raw, 'e164' => $e164, 'search' => $search];
    }

    /**
     * @return array<string, string>
     */
    private static function digitMap(): array
    {
        if (self::$digitMap === null) {
            self::$digitMap = [];

            for ($i = 0; $i <= 9; $i++) {
                self::$digitMap[mb_chr(0x0660 + $i, 'UTF-8')] = (string) $i;   // Arabic-Indic
                self::$digitMap[mb_chr(0x06F0 + $i, 'UTF-8')] = (string) $i;   // Persian
            }
        }

        return self::$digitMap;
    }
}