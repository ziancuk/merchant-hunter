<?php

use App\Services\Dedupe\NameNormalizer;
use App\Services\Dedupe\Similarity;

it('normalises case, diacritics, punctuation and generic words', function () {
    expect(NameNormalizer::normalize('Toko  Bérkah, Jaya!!'))->toBe('berkah jaya');
    expect(NameNormalizer::normalize('PT. Sinar Mas (CV)'))->toBe('sinar mas');
    expect(NameNormalizer::normalize('Warung Makan Sederhana'))->toBe('makan sederhana');
    expect(NameNormalizer::normalize('Café Kopi Senja'))->toBe('kopi senja');
});

it('keeps a name that consists only of generic words', function () {
    expect(NameNormalizer::normalize('Toko'))->toBe('toko');
    expect(NameNormalizer::normalize('  '))->toBe('');
});

it('scores identical names as 1 and empty names as 0', function () {
    expect(Similarity::names('Toko Maju Jaya', 'maju jaya'))->toBe(1.0);
    expect(Similarity::names('', 'x'))->toBe(0.0);
    expect(Similarity::normalized('abc', ''))->toBe(0.0);
});

it('scores small typos high and unrelated names low', function () {
    expect(Similarity::names('Kopi Senja Raya', 'Kopi Senja Rayaa'))->toBeGreaterThan(0.9);
    expect(Similarity::names('Kopi Senja', 'Senja Kopi'))->toBeGreaterThan(0.65);
    expect(Similarity::names('Apotek Sehat', 'Bengkel Motor Jaya'))->toBeLessThan(0.5);
});

it('computes known jaro-winkler reference values', function () {
    expect(round(Similarity::jaroWinkler('martha', 'marhta'), 3))->toBe(0.961);
    expect(round(Similarity::jaroWinkler('dixon', 'dicksonx'), 3))->toBe(0.813);
    expect(Similarity::jaro('', 'a'))->toBe(0.0);
    expect(Similarity::jaro('abc', 'xyz'))->toBe(0.0);
});

it('token set ratio ignores order and duplicate tokens', function () {
    expect(Similarity::tokenSetRatio('a b c', 'c b a'))->toBe(1.0);
    expect(Similarity::tokenSetRatio('kopi kopi senja', 'senja kopi'))->toBe(1.0);
    expect(Similarity::tokenSetRatio('', 'x'))->toBe(0.0);
    expect(Similarity::tokenSetRatio('kopi', 'kopi senja raya'))->toBeGreaterThan(0.9);
});
