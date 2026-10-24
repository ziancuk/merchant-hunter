<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\LifecycleStatus;
use App\Enums\PipelineStatus;
use App\Models\DailyDigest;
use App\Models\Prospect;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** Daily digest content: new open, hot, and follow-up lists (D-64). */
final class DigestQuery
{
    public const LIMIT = 20;

    public function __construct(private readonly SettingsService $settings) {}

    /** @return array{date: string, new_open: list<array<string, mixed>>, hot: list<array<string, mixed>>, follow_up: list<array<string, mixed>>} */
    public function build(CarbonInterface $date): array
    {
        $hotThreshold = $this->settings->int('digest.hot_threshold');
        $followUpDays = $this->settings->int('digest.follow_up_days');
        $cutoff = $date->copy()->endOfDay()->subDays($followUpDays);

        $newOpen = $this->open()
            ->where('is_new_open', true)->where('is_baseline', false)
            ->where('first_seen_at', '<=', $date->copy()->endOfDay());

        $hot = $this->open()
            ->where('pipeline_status', PipelineStatus::New->value)
            ->where('score', '>=', $hotThreshold);

        $followUp = $this->open()
            ->whereIn('pipeline_status', [PipelineStatus::Contacted->value, PipelineStatus::Demo->value])
            ->whereDoesntHave('activities', fn ($q) => $q->where('created_at', '>', $cutoff));

        return [
            'date' => $date->format('Y-m-d'),
            'new_open' => $this->rows($newOpen),
            'hot' => $this->rows($hot),
            'follow_up' => $this->rows($followUp),
        ];
    }

    /** Stored digest for a date, or null. @return array<string, mixed>|null */
    public function stored(CarbonInterface $date): ?array
    {
        return DailyDigest::query()->whereDate('digest_date', $date->format('Y-m-d'))->first()?->payload;
    }

    /** @return Builder<Prospect> */
    private function open(): Builder
    {
        return Prospect::query()
            ->where('lifecycle_status', LifecycleStatus::Open->value)
            ->where('do_not_contact', false);
    }

    /**
     * @param  Builder<Prospect>  $q
     * @return list<array{id: int, name: string, score: int}>
     */
    private function rows(Builder $q): array
    {
        return $q->orderByDesc('score')->orderBy('id')->limit(self::LIMIT)
            ->get(['id', 'name', 'score'])
            ->map(fn (Prospect $p) => ['id' => $p->id, 'name' => $p->name, 'score' => $p->score])
            ->all();
    }
}
