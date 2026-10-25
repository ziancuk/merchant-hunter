<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GeocodeStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;
use App\Models\Province;
use App\Services\Master\ProspectReconciler;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Support\Facades\DB;

/** CSV outlet import: code,name,address,city,province,lat,lng. Geocoding happens later through jobs. */
final class ImportOutletsAction
{
    public const MAX_ROWS = 5000;

    private const CHUNK = 200;

    private const REQUIRED = ['code', 'name', 'city', 'province'];

    public function __construct(private readonly ProspectReconciler $reconciler) {}

    /**
     * @return array{created: int, updated: int, geocoded: int, needs_review: int, failed: int, queued: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new BusinessRuleException(__('api.import.unreadable'), 'unreadable_file');
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header === false || $header === [null]) {
                throw new BusinessRuleException(__('api.import.empty'), 'empty_file');
            }
            $header = array_map(fn ($h) => strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))), $header);
            $missing = array_diff(self::REQUIRED, $header);
            if ($missing !== []) {
                throw new BusinessRuleException(__('api.import.missing_columns', ['columns' => implode(', ', $missing)]), 'missing_columns');
            }

            return $this->process($handle, $header);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $header
     */
    private function process($handle, array $header): array
    {
        $provinces = $this->provinceIndex();
        $result = ['created' => 0, 'updated' => 0, 'geocoded' => 0, 'needs_review' => 0, 'failed' => 0, 'queued' => 0, 'errors' => []];
        $queue = [];
        $withCoords = [];
        $touched = [];
        $chunk = [];

        $line = 1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if ($line - 1 > self::MAX_ROWS) {
                $result['errors'][] = ['row' => $line, 'message' => __('api.import.row_limit', ['max' => self::MAX_ROWS])];
                $result['failed']++;
                break;
            }

            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }

            $error = $this->validate($row, $provinces);
            if ($error !== null) {
                $result['errors'][] = ['row' => $line, 'message' => $error];
                $result['failed']++;

                continue;
            }

            $chunk[] = $row;
            if (count($chunk) >= self::CHUNK) {
                $this->saveChunk($chunk, $provinces, $result, $queue, $withCoords, $touched);
                $chunk = [];
            }
        }
        $this->saveChunk($chunk, $provinces, $result, $queue, $withCoords, $touched);

        foreach ($queue as $outletId) {
            GeocodeOutletJob::dispatch($outletId);
        }
        $result['queued'] = count($queue);
        $result['needs_review'] = $touched === [] ? 0 : Outlet::query()->whereIn('id', $touched)
            ->where('geocode_status', GeocodeStatus::NeedsReview->value)->count();

        if ($withCoords !== []) {
            $this->reconciler->reconcile($withCoords);
            WhitespaceCache::bump();
        }

        return $result;
    }

    /**
     * Saves one chunk of validated rows in a single transaction, with one lookup query for the existing codes.
     * Existing outlets keep their `is_active` flag: a deactivated outlet is never silently reactivated by an import.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, int>  $provinces
     * @param  array<string, mixed>  $result
     * @param  list<int>  $queue
     * @param  list<int>  $withCoords
     * @param  list<int>  $touched
     */
    private function saveChunk(array $rows, array $provinces, array &$result, array &$queue, array &$withCoords, array &$touched): void
    {
        if ($rows === []) {
            return;
        }

        DB::transaction(function () use ($rows, $provinces, &$result, &$queue, &$withCoords, &$touched) {
            /** @var array<string, Outlet> $byCode */
            $byCode = Outlet::query()->whereIn('code', array_column($rows, 'code'))->get()->keyBy('code')->all();

            foreach ($rows as $row) {
                $hasCoords = ($row['lat'] ?? '') !== '' && ($row['lng'] ?? '') !== '';
                $attributes = [
                    'name' => $row['name'],
                    'address' => ($row['address'] ?? '') !== '' ? $row['address'] : null,
                    'city' => $row['city'],
                    'province_id' => $provinces[mb_strtolower($row['province'])],
                ];

                $outlet = $byCode[$row['code']] ?? null;
                if ($outlet === null) {
                    $outlet = new Outlet(['code' => $row['code'], 'is_active' => true, 'is_simulated' => false, 'geocode_status' => GeocodeStatus::Ok->value] + $attributes);
                    $result['created']++;
                } else {
                    $outlet->fill($attributes);
                    $result['updated']++;
                }

                if ($hasCoords) {
                    $outlet->fill(['lat' => (float) $row['lat'], 'lng' => (float) $row['lng'], 'geocode_source' => 'csv',
                        'geocode_status' => GeocodeStatus::Ok->value, 'geocode_score' => null, 'geocode_candidates' => null]);
                }
                $outlet->save();
                $byCode[$row['code']] = $outlet; // a repeated code later in the file updates the same row
                $touched[] = $outlet->id;

                if ($outlet->lat !== null && $outlet->lng !== null) {
                    $result['geocoded']++;
                    if ($hasCoords) {
                        $withCoords[] = $outlet->id;
                    }
                } else {
                    $queue[] = $outlet->id;
                }
            }
        });
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $provinces
     */
    private function validate(array $row, array $provinces): ?string
    {
        foreach (self::REQUIRED as $field) {
            if (($row[$field] ?? '') === '') {
                return __('api.import.required', ['field' => $field]);
            }
        }
        if (mb_strlen($row['code']) > 20) {
            return __('api.import.code_max');
        }
        if (mb_strlen($row['name']) > 160) {
            return __('api.import.name_max');
        }
        if (mb_strlen($row['city']) > 80) {
            return __('api.import.city_max');
        }
        if (! isset($provinces[mb_strtolower($row['province'])])) {
            return __('api.import.unknown_province', ['province' => $row['province']]);
        }

        $lat = $row['lat'] ?? '';
        $lng = $row['lng'] ?? '';
        if (($lat === '') !== ($lng === '')) {
            return __('api.import.latlng_pair');
        }
        if ($lat !== '') {
            if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                return __('api.import.bad_coordinates');
            }
        }

        return null;
    }

    /** @return array<string, int> lower-cased province name or code => id */
    private function provinceIndex(): array
    {
        $index = [];
        foreach (Province::query()->get(['id', 'name', 'code']) as $p) {
            $index[mb_strtolower($p->name)] = $p->id;
            $index[mb_strtolower($p->code)] = $p->id;
        }

        return $index;
    }
}
