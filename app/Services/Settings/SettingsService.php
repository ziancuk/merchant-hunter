<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Impact\ImpactCalculator;

/** Typed access to the key/value `settings` table with defaults from config/hunter.php. */
final class SettingsService
{
    public function get(string $key): mixed
    {
        return Setting::read($key, config('hunter.settings_defaults')[$key] ?? null);
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
        Setting::write($key, $value);
    }

    /**
     * Nested shape used by GET/PUT /api/settings and the Master/Settings page.
     *
     * @return array<string, array<string, mixed>>
     */
    public function structured(): array
    {
        return [
            'contact' => [
                'warn_days' => $this->int('contact.warn_days'),
                'min_interval_days' => $this->int('contact.min_interval_days'),
                'max_overrides' => $this->int('contact.max_overrides'),
            ],
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
        return $this->structured()['contact'];
    }

    /** @return array<string, float|int> */
    public function impactAssumptions(): array
    {
        $saved = (array) Setting::read('impact.assumptions', []);

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
