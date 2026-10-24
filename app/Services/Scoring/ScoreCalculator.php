<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\DTO\ScoreInput;
use App\DTO\ScoreResult;
use App\Services\Settings\SettingsService;
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

    /** @param array<string, mixed> $overrides */
    public function __construct(
        private readonly ?SettingsService $settings = null,
        private readonly array $overrides = [],
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
        $labels = [
            'category' => 'Kecocokan kategori',
            'quality' => 'Kualitas (rating & ulasan)',
            'proximity' => 'Kedekatan dengan outlet',
            'density' => 'Kepadatan area',
            'contact' => 'Kelengkapan kontak',
            'new' => 'Baru buka',
        ];

        $total = 0.0;
        $breakdown = [];
        foreach ($components as $key => [$value, $reason]) {
            $max = (int) $w[$key];
            $points = $closed ? 0.0 : $max * $value;
            $total += $points;
            $breakdown[] = [
                'key' => $key,
                'label' => $labels[$key],
                'value' => round($closed ? 0.0 : $value, 4),
                'max' => $max,
                'points' => round($points, 2),
                'reason' => $closed ? 'Usaha tutup: skor 0' : $reason,
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
        if ($this->settings !== null) {
            try {
                $stored = (array) ($this->settings->get('scoring.params') ?? []);
            } catch (Throwable) {
                $stored = [];
            }
        }

        return $this->resolved = array_replace_recursive(self::DEFAULTS, $stored, $this->overrides);
    }

    /** @return array{float, string} */
    private function category(ScoreInput $in): array
    {
        return [self::clamp($in->categoryWeight / 100), 'Bobot kategori '.$in->categoryWeight];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string}
     */
    private function quality(ScoreInput $in, array $p): array
    {
        if (! $in->ratingCounts) {
            return [(float) $p['unrated_value'], 'Rating tidak dihitung (nilai netral '.$p['unrated_value'].')'];
        }
        if ($in->rating === null) {
            return [(float) $p['unrated_value'], 'Belum ada rating (nilai netral '.$p['unrated_value'].')'];
        }

        $span = max(0.0001, (float) $p['rating_ceiling'] - (float) $p['rating_floor']);
        $r = self::clamp(($in->rating - (float) $p['rating_floor']) / $span);
        $reviews = max(0, (int) ($in->reviewCount ?? 0));
        $cap = max(1, (int) $p['reviews_cap']);
        $v = self::clamp(log10(1 + $reviews) / log10(1 + $cap));
        $value = self::clamp((float) $p['rating_weight'] * $r + (float) $p['reviews_weight'] * $v);

        return [$value, 'Rating '.number_format($in->rating, 1, '.', '').' dari '.$reviews.' ulasan'];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string}
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
        $reason = $neighbors > 0
            ? $neighbors.' outlet Hitachi dalam 500 m'
            : 'Tidak ada outlet Hitachi dalam 500 m';
        $reason .= $in->nearestOutletDistanceM === null
            ? '; jarak ke outlet belum diketahui'
            : '; outlet terdekat '.$in->nearestOutletDistanceM.' m';

        return [$value, $reason];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string}
     */
    private function density(ScoreInput $in, array $p): array
    {
        $cap = max(1, (int) $p['density_cap']);
        $n = max(0, $in->areaDensity);

        return [self::clamp($n / $cap), $n.' prospek lain dalam 500 m'];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string}
     */
    private function contact(ScoreInput $in, array $p): array
    {
        $value = ($in->hasPhone ? (float) $p['contact_phone'] : 0.0)
            + ($in->hasWebsite ? (float) $p['contact_website'] : 0.0)
            + ($in->hasAddress ? (float) $p['contact_address'] : 0.0);

        $have = array_keys(array_filter(['telepon' => $in->hasPhone, 'website' => $in->hasWebsite, 'alamat' => $in->hasAddress]));

        return [self::clamp($value), $have === [] ? 'Belum ada telepon, website, atau alamat' : 'Tersedia: '.implode(', ', $have)];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{float, string}
     */
    private function newOpen(ScoreInput $in, array $p): array
    {
        if (! $in->isNewOpen) {
            return [0.0, 'Bukan usaha baru buka'];
        }
        $days = max(1, (int) $p['new_days']);
        $age = max(0, $in->ageDays);
        $value = self::clamp(1 - $age / $days);

        return [$value, $value > 0 ? 'Baru buka '.$age.' hari lalu' : 'Baru buka, tetapi sudah lewat '.$days.' hari'];
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
