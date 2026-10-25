<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Prospect;
use App\Repositories\ProspectFilter;
use Generator;
use Illuminate\Support\Carbon;

/** Prospect CSV rows streamed straight from a joined query (no Eloquent hydration, constant memory). */
final class ProspectCsvExport
{
    public const HEADERS = [
        'id', 'nama', 'kategori', 'kota', 'provinsi', 'alamat', 'telepon', 'simulasi_telepon', 'rating',
        'jumlah_review', 'skor', 'status', 'ditangani_oleh', 'terakhir_dihubungi', 'jarak_outlet_m',
    ];

    public function filename(): string
    {
        return 'prospek-'.now()->format('Ymd').'.csv';
    }

    /**
     * Cell sanitisation (formula neutralisation) is applied by CsvExporter::download via CsvExporter::sanitize.
     *
     * @return Generator<int, list<mixed>>
     */
    public function rows(ProspectFilter $filter): Generator
    {
        $query = $filter->apply(Prospect::query())->toBase()
            ->leftJoin('categories', 'categories.id', '=', 'prospects.category_id')
            ->leftJoin('provinces', 'provinces.id', '=', 'prospects.province_id')
            ->leftJoin('users', 'users.id', '=', 'prospects.handled_by')
            ->select([
                'prospects.id', 'prospects.name', 'categories.name as category_name', 'prospects.city',
                'provinces.name as province_name', 'prospects.address', 'prospects.phone', 'prospects.phone_simulated',
                'prospects.rating', 'prospects.review_count', 'prospects.score', 'prospects.pipeline_status',
                'users.name as handler_name', 'prospects.last_contacted_at', 'prospects.nearest_outlet_distance_m',
            ])
            ->orderBy('prospects.id');

        foreach ($query->cursor() as $r) {
            yield self::row($r);
        }
    }

    /**
     * Maps a raw joined row to the CSV columns (same values the Eloquent-based export produced).
     *
     * @return list<mixed>
     */
    public static function row(object $r): array
    {
        return [
            (int) $r->id,
            $r->name,
            $r->category_name,
            $r->city,
            $r->province_name,
            $r->address,
            $r->phone,
            $r->phone_simulated ? 1 : 0,
            $r->rating === null ? null : (float) $r->rating,
            $r->review_count === null ? null : (int) $r->review_count,
            (int) $r->score,
            $r->pipeline_status,
            $r->handler_name,
            $r->last_contacted_at === null ? null : Carbon::parse($r->last_contacted_at, 'UTC')->toIso8601ZuluString(),
            $r->nearest_outlet_distance_m === null ? null : (int) $r->nearest_outlet_distance_m,
        ];
    }
}
