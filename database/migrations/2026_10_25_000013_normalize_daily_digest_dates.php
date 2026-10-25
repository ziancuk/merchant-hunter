<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DailyDigest now stores `digest_date` as a plain Y-m-d (cast `date:Y-m-d`) so lookups can use an index-friendly
 * `where digest_date = ?` instead of whereDate(). Older rows written by the plain `date` cast on SQLite carry a
 * " 00:00:00" suffix; normalise them so equality lookups keep finding them (no-op on MySQL/PostgreSQL date columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('daily_digests')->orderBy('id')->each(function (object $row) {
            $date = Carbon::parse($row->digest_date)->toDateString();
            if ($date !== $row->digest_date) {
                DB::table('daily_digests')->where('id', $row->id)->update(['digest_date' => $date]);
            }
        });
    }

    public function down(): void
    {
        // Data normalisation only; nothing to undo.
    }
};
