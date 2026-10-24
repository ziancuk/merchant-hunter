<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\PipelineStatus;
use App\Http\Resources\CardResource;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PipelineQuery
{
    public const SORTS = ['score' => 'prospects.score', 'last_contacted_at' => 'prospects.last_contacted_at', 'name' => 'prospects.name'];

    /**
     * Kanban columns: total + first cards per status (keyset cursor "score:id" for a single status).
     *
     * @return array<string, array{total: int, cards: list<array<string, mixed>>, next_cursor: string|null}>
     */
    public function columns(ProspectFilter $filter, int $perStatus = 50, ?string $onlyStatus = null, ?string $cursor = null): array
    {
        $statuses = $onlyStatus !== null ? [PipelineStatus::from($onlyStatus)] : PipelineStatus::cases();

        $totals = $filter->apply(Prospect::query())
            ->selectRaw('prospects.pipeline_status as status, count(*) as total')
            ->groupBy('prospects.pipeline_status')
            ->toBase()->pluck('total', 'status');

        $columns = [];
        foreach ($statuses as $status) {
            $q = $this->cardsQuery($filter)->where('prospects.pipeline_status', $status->value)
                ->orderByDesc('prospects.score')->orderByDesc('prospects.id');

            if ($onlyStatus !== null && $cursor !== null && preg_match('/^(\d+):(\d+)$/', $cursor, $m)) {
                $q->where(fn ($w) => $w->where('prospects.score', '<', (int) $m[1])
                    ->orWhere(fn ($x) => $x->where('prospects.score', (int) $m[1])->where('prospects.id', '<', (int) $m[2])));
            }

            $rows = $q->limit($perStatus + 1)->get();
            $more = $rows->count() > $perStatus;
            $rows = $rows->take($perStatus);
            $last = $rows->last();

            $columns[$status->value] = [
                'total' => (int) ($totals[$status->value] ?? 0),
                'cards' => CardResource::collection($rows)->resolve(),
                'next_cursor' => $more && $last ? $last->score.':'.$last->id : null,
            ];
        }

        return $columns;
    }

    /** @return LengthAwarePaginator<int, Prospect> */
    public function table(ProspectFilter $filter, string $sort, string $dir, int $perPage)
    {
        $column = self::SORTS[$sort] ?? self::SORTS['score'];
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $q = $this->cardsQuery($filter);
        if ($sort === 'last_contacted_at') {
            // never-contacted rows stay last regardless of direction
            $q->orderByRaw('prospects.last_contacted_at is null');
        }
        $q->orderBy($column, $dir);

        return $q->orderBy('prospects.id')->paginate($perPage);
    }

    /** @return Builder<Prospect> */
    private function cardsQuery(ProspectFilter $filter): Builder
    {
        return $filter->apply(Prospect::query())
            ->with(['category:id,name', 'handler:id,name'])
            ->select('prospects.*');
    }
}
