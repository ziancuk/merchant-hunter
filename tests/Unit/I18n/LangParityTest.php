<?php

use Illuminate\Support\Arr;

/** @return array<string, list<string>> group => flattened keys, for one language directory */
function lang_keys(string $locale): array
{
    $out = [];
    foreach (glob(dirname(__DIR__, 3).'/lang/'.$locale.'/*.php') ?: [] as $file) {
        $out[basename($file, '.php')] = array_keys(Arr::dot(require $file));
    }
    ksort($out);

    return $out;
}

it('has the same lang files in id and en', function () {
    expect(array_keys(lang_keys('id')))->toBe(array_keys(lang_keys('en')));
});

it('defines every key of lang/id in lang/en and vice versa', function () {
    $id = lang_keys('id');
    $en = lang_keys('en');

    foreach ($id as $group => $keys) {
        $other = $en[$group] ?? [];
        expect(array_values(array_diff($keys, $other)))->toBe([], "keys in id/{$group}.php missing from en/{$group}.php")
            ->and(array_values(array_diff($other, $keys)))->toBe([], "keys in en/{$group}.php missing from id/{$group}.php");
    }
});

it('uses the same :placeholders in both languages for every key', function () {
    $placeholders = fn (mixed $v): array => is_string($v) && preg_match_all('/:[a-z_]+/', $v, $m) ? array_values(array_unique($m[0])) : [];

    foreach (glob(dirname(__DIR__, 3).'/lang/id/*.php') ?: [] as $file) {
        $group = basename($file, '.php');
        $id = Arr::dot(require $file);
        $en = Arr::dot(require dirname(__DIR__, 3).'/lang/en/'.$group.'.php');
        foreach ($id as $key => $text) {
            $a = $placeholders($text);
            $b = $placeholders($en[$key] ?? null);
            sort($a);
            sort($b);
            expect($b)->toBe($a, "placeholders differ for {$group}.{$key}");
        }
    }
});
