<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GeocodeStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\GeocodeOutletJob;
use App\Models\Outlet;
use App\Models\Province;
use App\Repositories\WhiteSpaceQuery;
use App\Services\Master\ProspectReconciler;
use Illuminate\Support\Facades\Cache;

/** CSV outlet import: code,name,address,city,province,lat,lng. Geocoding happens later through jobs. */
final class ImportOutletsAction
{
    public const MAX_ROWS = 5000;

    private const REQUIRED = ['code', 'name', 'city', 'province'];

    public function __construct(private readonly ProspectReconciler $reconciler) {}

    /**
     * @return array{created: int, updated: int, geocoded: int, needs_review: int, failed: int, queued: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new BusinessRuleException('Berkas CSV tidak dapat dibaca.', 'unreadable_file');
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header === false || $header === [null]) {
                throw new BusinessRuleException('Berkas CSV kosong.', 'empty_file');
            }
            $header = array_map(fn ($h) => strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))), $header);
            $missing = array_diff(self::REQUIRED, $header);
            if ($missing !== []) {
                throw new BusinessRuleException('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.', 'missing_columns');
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

        $line = 1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if ($line - 1 > self::MAX_ROWS) {
                $result['errors'][] = ['row' => $line, 'message' => 'Melebihi batas '.self::MAX_ROWS.' baris; sisanya diabaikan.'];
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

            $hasCoords = $row['lat'] !== '' && $row['lng'] !== '';
            $outlet = Outlet::query()->where('code', $row['code'])->first();
            $attributes = [
                'name' => $row['name'],
                'address' => ($row['address'] ?? '') !== '' ? $row['address'] : null,
                'city' => $row['city'],
                'province_id' => $provinces[mb_strtolower($row['province'])],
                'is_active' => true,
            ];

            if ($outlet === null) {
                $outlet = new Outlet(['code' => $row['code'], 'is_simulated' => false, 'geocode_status' => GeocodeStatus::Ok->value] + $attributes);
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

        foreach ($queue as $outletId) {
            GeocodeOutletJob::dispatch($outletId);
        }
        $result['queued'] = count($queue);
        $result['needs_review'] = $touched === [] ? 0 : Outlet::query()->whereIn('id', $touched)
            ->where('geocode_status', GeocodeStatus::NeedsReview->value)->count();

        if ($withCoords !== []) {
            $this->reconciler->reconcile($withCoords);
            Cache::forever(WhiteSpaceQuery::VERSION_KEY, (int) Cache::get(WhiteSpaceQuery::VERSION_KEY, 1) + 1);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $provinces
     */
    private function validate(array $row, array $provinces): ?string
    {
        foreach (self::REQUIRED as $field) {
            if (($row[$field] ?? '') === '') {
                return 'Kolom '.$field.' wajib diisi.';
            }
        }
        if (mb_strlen($row['code']) > 20) {
            return 'Kode maksimal 20 karakter.';
        }
        if (mb_strlen($row['name']) > 160) {
            return 'Nama maksimal 160 karakter.';
        }
        if (mb_strlen($row['city']) > 80) {
            return 'Kota maksimal 80 karakter.';
        }
        if (! isset($provinces[mb_strtolower($row['province'])])) {
            return 'Provinsi "'.$row['province'].'" tidak dikenal.';
        }

        $lat = $row['lat'] ?? '';
        $lng = $row['lng'] ?? '';
        if (($lat === '') !== ($lng === '')) {
            return 'Isi lat dan lng bersamaan, atau kosongkan keduanya.';
        }
        if ($lat !== '') {
            if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                return 'Koordinat tidak valid.';
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
