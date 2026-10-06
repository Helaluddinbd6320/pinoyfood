<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validNumbers(): array
    {
        $arabic = "\u{0660}\u{0665}\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}";   // 0501234567
        $persian = "\u{06F0}\u{06F5}\u{06F0}\u{06F1}\u{06F2}\u{06F3}\u{06F4}\u{06F5}\u{06F6}\u{06F7}";   // 0501234567

        return [
            // Saudi
            'sa national with 0' => ['0501234567', '+966501234567'],
            'sa plus, spaces' => ['+966 50 123 4567', '+966501234567'],
            'sa 00 prefix' => ['00966501234567', '+966501234567'],
            'sa nine digits' => ['501234567', '+966501234567'],
            'sa 966 without plus' => ['966501234567', '+966501234567'],
            'sa already e164' => ['+966501234567', '+966501234567'],
            'sa dashes' => ['050-123-4567', '+966501234567'],
            'sa brackets' => ['(050) 123 4567', '+966501234567'],
            'sa dots' => ['050.123.4567', '+966501234567'],
            'sa outer spaces' => ['  0501234567  ', '+966501234567'],
            'sa plus with dashes' => ['+966-50-123-4567', '+966501234567'],
            'sa arabic-indic digits' => [$arabic, '+966501234567'],
            'sa persian digits' => [$persian, '+966501234567'],
            'sa arabic digits in country code' => ["+\u{0669}\u{0666}\u{0666}501234567", '+966501234567'],
            'sa LRM and RLM marks' => ["\u{200E}+966 50 123 4567\u{200F}", '+966501234567'],
            'sa bidi embedding marks' => ["\u{202A}0501234567\u{202C}", '+966501234567'],
            'sa another prefix' => ['0555 123 456', '+966555123456'],

            // Philippines
            'ph plus, spaces' => ['+63 917 123 4567', '+639171234567'],
            'ph national dashes' => ['0917-123-4567', '+639171234567'],
            'ph national with 0' => ['09171234567', '+639171234567'],
            'ph ten digits' => ['9171234567', '+639171234567'],
            'ph 63 without plus' => ['639171234567', '+639171234567'],
            'ph 00 prefix' => ['00639171234567', '+639171234567'],

            // Other country, kept as a valid number
            'uk mobile' => ['+44 7400 123456', '+447400123456'],
        ];
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function unusableNumbers(): array
    {
        return [
            // input, expected phone_search
            'too short' => ['12345', '12345'],
            'saudi code but truncated' => ['+966 50 123', '96650123'],
            'too many digits' => ['050123456789012', '456789012'],
            'letters only' => ['abc', null],
            'emoji text' => ["call me \u{1F600}", null],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_valid_formats_become_e164(string $input, string $expected): void
    {
        $result = (new PhoneNormalizer())->normalize($input);

        $this->assertSame($expected, $result['e164'], "Input: {$input}");
    }

    public function test_table_has_at_least_20_formats(): void
    {
        $this->assertGreaterThanOrEqual(20, count(self::validNumbers()));
    }

    #[DataProvider('unusableNumbers')]
    public function test_unusable_numbers_keep_raw_and_never_throw(string $input, ?string $expectedSearch): void
    {
        $result = (new PhoneNormalizer())->normalize($input);

        $this->assertNull($result['e164']);
        $this->assertSame($input, $result['raw']);
        $this->assertSame($expectedSearch, $result['search']);
    }

    public function test_blank_input_gives_all_nulls(): void
    {
        foreach ([null, '', '   '] as $input) {
            $this->assertSame(
                ['raw' => null, 'e164' => null, 'search' => null],
                (new PhoneNormalizer())->normalize($input),
            );
        }
    }

    public function test_search_is_the_last_nine_digits_for_every_saudi_format(): void
    {
        $normalizer = new PhoneNormalizer();

        foreach (['0501234567', '+966 50 123 4567', '00966501234567', '501234567', '966501234567'] as $input) {
            $this->assertSame('501234567', $normalizer->normalize($input)['search'], "Input: {$input}");
        }

        $this->assertSame('171234567', $normalizer->normalize('+63 917 123 4567')['search']);
    }

    public function test_raw_is_kept_as_typed_but_trimmed_and_capped_at_40_characters(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('050 123 4567', $normalizer->normalize('  050 123 4567 ')['raw']);
        $this->assertSame(40, mb_strlen($normalizer->normalize(str_repeat('1', 60))['raw']));
    }

    public function test_odd_inputs_never_throw(): void
    {
        $normalizer = new PhoneNormalizer();

        foreach (['+', '++', '00', '-', "\u{0660}", '+999999999999999999999', "\xB1\x31"] as $input) {
            $result = $normalizer->normalize($input);

            $this->assertNull($result['e164'], 'Input: '.bin2hex($input));
        }
    }

    public function test_whatsapp_digits_have_no_plus(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('966501234567', $normalizer->whatsappDigits('+966501234567'));
        $this->assertNull($normalizer->whatsappDigits(null));
    }
}