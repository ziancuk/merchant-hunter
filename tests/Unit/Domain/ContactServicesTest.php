<?php

use App\Services\Contact\MessageBuilder;
use App\Services\Contact\WhatsAppNormalizer;

it('normalises Indonesian mobile numbers to 62 digits', function (string $raw) {
    expect(WhatsAppNormalizer::normalize($raw))->toBe('6281255550101');
})->with([
    '08 prefix' => '081255550101',
    'dashes' => '0812-5555-0101',
    'spaces' => '0812 5555 0101',
    'plus 62' => '+6281255550101',
    '62 prefix' => '6281255550101',
    'bare 8' => '81255550101',
    'plus with separators' => '+62 812-5555-0101',
    'parentheses and dots' => '(0812).5555.0101',
]);

it('rejects landlines, short, long and garbage numbers', function (?string $raw) {
    expect(WhatsAppNormalizer::normalize($raw))->toBeNull();
})->with([
    'jakarta landline' => '021-5551234',
    'landline with area code' => '(031) 555 1234',
    'too short' => '0812',
    'too long' => '0812555501010101010',
    'simulated 0999 range' => '0999-1234-5678',
    'letters' => 'abc',
    'empty' => '',
    'null' => null,
    'foreign' => '+14155550100',
]);

it('fills the template placeholders', function () {
    $m = (new MessageBuilder)->build('Halo {nama_bisnis}, saya {nama_pengirim} ({kategori}). {nama_bisnis}!', 'Kopi Senja', 'Ayu', 'Kafe');

    expect($m)->toBe('Halo Kopi Senja, saya Ayu (Kafe). Kopi Senja!');
});

it('keeps unknown braces untouched when building', function () {
    expect((new MessageBuilder)->build('{x} {nama_bisnis}', 'A', 'B', 'C'))->toBe('{x} A');
});

it('validates templates by their placeholders', function (string $tpl, bool $valid) {
    expect(MessageBuilder::isValidTemplate($tpl))->toBe($valid);
})->with([
    ['Halo {nama_bisnis}', true],
    ['Halo {nama_bisnis} dari {nama_pengirim}, {kategori}', true],
    ['Tanpa placeholder', false],
    ['Halo {nama_bisnis} {unknown}', false],
    ['Halo {}', false],
]);

it('accepts the simulated 0999 range only when allowed', function () {
    expect(WhatsAppNormalizer::normalize('0999-1234-5678'))->toBeNull();
    expect(WhatsAppNormalizer::normalize('0999-1234-5678', true))->toBe('62999'.'12345678');
});
