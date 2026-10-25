<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Impact\ImpactCalculator;

/** Typed access to the key/value `settings` table with defaults from config/hunter.php. */
final class SettingsService
{
    /** Bumped whenever any Setting row is written (also by direct Setting::write calls), so memoized copies never go stale. */
    private static int $generation = 0;

    /** @var array<string, mixed>|null */
    private ?array $all = null;

    private int $loadedGeneration = -1;

    /** Drop every memoized copy; called from the Setting model events. */
    public static function invalidate(): void
    {
        self::$generation++;
    }

    public function get(string $key): mixed
    {
        return $this->stored($key, config('hunter.settings_defaults')[$key] ?? null);
    }

    /** One query per request/instance: all settings rows are loaded once and reused. */
    private function stored(string $key, mixed $default): mixed
    {
        if ($this->all === null || $this->loadedGeneration !== self::$generation) {
            $this->all = [];
            foreach (Setting::query()->get() as $row) {
                $this->all[$row->key] = $row->value;
            }
            $this->loadedGeneration = self::$generation;
        }

        return array_key_exists($key, $this->all) ? $this->all[$key] : $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::write($key, $value); // model events invalidate the memoized copy
    }

    /**
     * Nested shape used by GET/PUT /api/settings and the Master/Settings page.
     *
     * @return array<string, array<string, mixed>>
     */
    public function structured(): array
    {
        return [
            'contact' => $this->contactRules(),
            'digest' => [
                'email_to' => $this->get('digest.email_to'),
                'hot_threshold' => $this->int('digest.hot_threshold'),
                'follow_up_days' => $this->int('digest.follow_up_days'),
            ],
            'sync' => ['max_outlets_per_run' => $this->int('sync.max_outlets_per_run')],
            'scoring' => ['params' => (array) ($this->get('scoring.params') ?? [])],
            'enrichment' => ['simulated' => $this->bool('enrichment.simulated')],
            'demo' => ['allow_simulated_whatsapp' => $this->bool('demo.allow_simulated_whatsapp')],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $values  validated, possibly partial
     * @return array<string, array<string, mixed>>
     */
    public function updateStructured(array $values): array
    {
        foreach ($values as $group => $items) {
            foreach ((array) $items as $name => $value) {
                $this->set($group.'.'.$name, $value);
            }
        }

        return $this->structured();
    }

    /** @return array{warn_days: int, min_interval_days: int, max_overrides: int} */
    public function contactRules(): array
    {
        return [
            'warn_days' => $this->int('contact.warn_days'),
            'min_interval_days' => $this->int('contact.min_interval_days'),
            'max_overrides' => $this->int('contact.max_overrides'),
        ];
    }

    /** @return array<string, float|int> */
    public function impactAssumptions(): array
    {
        $saved = (array) $this->stored('impact.assumptions', []);

        return array_replace(ImpactCalculator::defaults(), array_intersect_key($saved, ImpactCalculator::defaults()));
    }

    /**
     * @param  array<string, float|int>  $values
     * @return array<string, float|int>
     */
    public function saveImpactAssumptions(array $values): array
    {
        $merged = array_replace($this->impactAssumptions(), array_intersect_key($values, ImpactCalculator::defaults()));
        $this->set('impact.assumptions', $merged);

        return $merged;
    }
}
