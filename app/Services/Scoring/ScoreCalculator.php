<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\DTO\ScoreInput;
use App\DTO\ScoreResult;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Lang;
use Throwable;

/**
 * Pure scoring formula (TRD §7):
 * score = round(25*category + 20*quality + 15*proximity + 15*density + 10*contact + 15*new).
 * Parameters are overridable through settings `scoring.params` (and the constructor, for tests).
 */
final class ScoreCalculator implements ProspectScorer
{
    public const DEFAULTS = [
        'weights' => ['category' => 25, 'quality' => 20, 'proximity' => 15, 'density' => 15, 'contact' => 10, 'new' => 15],
        'rating_floor' => 3.0,
        'rating_ceiling' => 5.0,
        'rating_weight' => 0.6,
        'reviews_weight' => 0.4,
        'reviews_cap' => 500,
        'unrated_value' => 0.25,
        'proximity_distance_m' => 2000,
        'proximity_distance_share' => 0.5,
        'neighbor_values' => [0.0, 0.6, 0.85, 1.0],
        'density_cap' => 25,
        'contact_phone' => 0.5,
        'contact_website' => 0.25,
        'contact_address' => 0.25,
        'new_days' => 30,
    ];

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    /**
     * @param  array<string, mixed>  $overrides  highest-priority parameter overrides (mostly for tests)
     * @param  bool  $pure  true = never read `scoring.params` from settings (plain unit use)
     */
    public function __construct(
        private readonly ?SettingsService $settings = null,
        private readonly array $overrides = [],
        private readonly bool $pure = false,
    ) {}

    public function score(ScoreInput $input): ScoreResult
    {
        $p = $this->params();
        $w = $p['weights'];
        $closed = $input->isClosed;

        $components = [
            'category' => $this->category($input),
            'quality' => $this->quality($input, $p),
            'proximity' => $this->proximity($input, $p),
            'density' => $this->density($input, $p),
            'contact' => $this->contact($input, $p),
            'new' => $this->newOpen($input, $p),
        ];
        $total = 0.0;
        $breakdown = [];
        foreach ($components as $key => [$value, $reasonKey, $reasonParams]) {
            $max = (int) $w[$key];
            $points = $closed ? 0.0 : $max * $value;
            $total += $points;
            $breakdown[] = [
                'key' => $key,
                'label' => __('scoring.labels.'.$key),
                'value' => round($closed ? 0.0 : $value, 4),
                'max' => $max,
                'points' => round($points, 2),
                'reason' => self::reason($closed ? 'closed' : $reasonKey, $closed ? [] : $reasonParams),
                // Structured form of the reason, persisted so it can be re-translated when read (see translateBreakdown()).
                'reason_key' => $closed ? 'closed' : $reasonKey,
                'reason_params' => $closed ? [] : $reasonParams,
            ];
        }

        $score = $closed ? 0 : max(0, min(100, (int) round($total)));

        return new ScoreResult($score, $breakdown);
    }

    /** @return array<string, mixed> */
    public function params(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = [];
        if (! $this->pure) {
            try {
                $settings = $this->settings ?? app(SettingsService::class);
                $stored = (array) ($settings->get('scoring.params') ?? []);
            } catch (Throwable) {
                $stored = [];
            }
        }

        return $this->resolved = array_replace_recursive(self::DEFAULTS, $stored, $this->overrides);
    }

    /** @return array{float, string, array<string, mixed>} */
    private function category(ScoreInput $in): array
    {
        return [self::clamp($in->categoryWeight / 100), 'category', ['weight' => $in->categoryWeight]];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string, array<string, mixed>}
     */
    private function quality(ScoreInput $in, array $p): array
    {
        if (! $in->ratingCounts) {
            return [(float) $p['unrated_value'], 'rating_ignored', ['value' => $p['unrated_value']]];
        }
        if ($in->rating === null) {
            return [(float) $p['unrated_value'], 'rating_missing', ['value' => $p['unrated_value']]];
        }

        $span = max(0.0001, (float) $p['rating_ceiling'] - (float) $p['rating_floor']);
        $r = self::clamp(($in->rating - (float) $p['rating_floor']) / $span);
        $reviews = max(0, (int) ($in->reviewCount ?? 0));
        $cap = max(1, (int) $p['reviews_cap']);
        $v = self::clamp(log10(1 + $reviews) / log10(1 + $cap));
        $value = self::clamp((float) $p['rating_weight'] * $r + (float) $p['reviews_weight'] * $v);

        return [$value, 'rating', ['rating' => number_format($in->rating, 1, '.', ''), 'reviews' => $reviews]];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string, array<string, mixed>}
     */
    private function proximity(ScoreInput $in, array $p): array
    {
        $share = (float) $p['proximity_distance_share'];
        $maxD = max(1, (int) $p['proximity_distance_m']);
        $neighbors = max(0, $in->neighborOutlets500m);
        $values = array_values((array) $p['neighbor_values']);
        $t = (float) ($values[min($neighbors, count($values) - 1)] ?? 0.0);

        $distancePart = $in->nearestOutletDistanceM === null
            ? 0.0
            : 1 - min(max(0, $in->nearestOutletDistanceM), $maxD) / $maxD;

        $value = self::clamp($share * $distancePart + (1 - $share) * $t);
        $reasonParams = ['neighbors' => $neighbors, 'distance' => $in->nearestOutletDistanceM];

        return [$value, 'proximity', $reasonParams];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string, array<string, mixed>}
     */
    private function density(ScoreInput $in, array $p): array
    {
        $cap = max(1, (int) $p['density_cap']);
        $n = max(0, $in->areaDensity);

        return [self::clamp($n / $cap), 'density', ['count' => $n]];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string, array<string, mixed>}
     */
    private function contact(ScoreInput $in, array $p): array
    {
        $value = ($in->hasPhone ? (float) $p['contact_phone'] : 0.0)
            + ($in->hasWebsite ? (float) $p['contact_website'] : 0.0)
            + ($in->hasAddress ? (float) $p['contact_address'] : 0.0);

        return [self::clamp($value), 'contact', ['phone' => $in->hasPhone, 'website' => $in->hasWebsite, 'address' => $in->hasAddress]];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string, array<string, mixed>}
     */
    private function newOpen(ScoreInput $in, array $p): array
    {
        if (! $in->isNewOpen) {
            return [0.0, 'not_new', []];
        }
        $days = max(1, (int) $p['new_days']);
        $age = max(0, $in->ageDays);
        $value = self::clamp(1 - $age / $days);

        return $value > 0 ? [$value, 'new_age', ['days' => $age]] : [$value, 'new_expired', ['days' => $days]];
    }

    /**
     * Human-readable reason in the current locale from its stable key and parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public static function reason(string $key, array $params = []): string
    {
        $t = static fn (string $k, array $r = []): string => __('scoring.reasons.'.$k, $r);

        return match ($key) {
            'proximity' => (($params['neighbors'] ?? 0) > 0
                ? $t('neighbors', ['count' => $params['neighbors']])
                : $t('no_neighbors'))
                .(($params['distance'] ?? null) === null
                    ? $t('distance_unknown')
                    : $t('nearest', ['distance' => $params['distance']])),
            'contact' => self::contactReason($params, $t),
            default => $t($key, $params),
        };
    }

    /**
     * Rewrites a stored breakdown (label/reason persisted in the language of the sync run) into the request language.
     * Rows written before reason keys existed keep their stored reason; the label always follows the component key.
     *
     * @param  list<array<string, mixed>>  $breakdown
     * @return list<array<string, mixed>>
     */
    public static function translateBreakdown(array $breakdown): array
    {
        return array_map(static function (array $row): array {
            if (isset($row['key']) && is_string($row['key']) && Lang::has('scoring.labels.'.$row['key'])) {
                $row['label'] = __('scoring.labels.'.$row['key']);
            }
            if (isset($row['reason_key']) && is_string($row['reason_key'])) {
                $row['reason'] = self::reason($row['reason_key'], (array) ($row['reason_params'] ?? []));
            }
            unset($row['reason_key'], $row['reason_params']);

            return $row;
        }, array_values($breakdown));
    }

    /** @param  array<string, mixed>  $params */
    private static function contactReason(array $params, callable $t): string
    {
        $have = [];
        foreach (['phone', 'website', 'address'] as $field) {
            if (! empty($params[$field])) {
                $have[] = $t('contact_'.$field);
            }
        }

        return $have === [] ? $t('contact_none') : $t('contact_have', ['items' => implode(', ', $have)]);
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
