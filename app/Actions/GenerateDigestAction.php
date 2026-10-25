<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyDigest;
use App\Repositories\DigestQuery;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Builds and stores the daily digest (idempotent per date) and optionally e-mails it. */
final class GenerateDigestAction
{
    public function __construct(
        private readonly DigestQuery $digests,
        private readonly SettingsService $settings,
    ) {}

    /** @return array{digest: DailyDigest, created: bool} */
    public function handle(CarbonInterface $date, bool $force = false): array
    {
        $day = $date->format('Y-m-d');
        $existing = DailyDigest::query()->where('digest_date', $day)->first();
        $created = false;

        if ($existing === null) {
            $existing = DailyDigest::query()->create(['digest_date' => $day, 'payload' => $this->digests->build($date)]);
            $created = true;
        } elseif ($force) {
            $existing->update(['payload' => $this->digests->build($date)]);
        }

        if ($existing->emailed_at === null) {
            $this->email($existing);
        }

        return ['digest' => $existing->refresh(), 'created' => $created];
    }

    private function email(DailyDigest $digest): void
    {
        $to = $this->settings->get('digest.email_to');
        $mailer = (string) config('mail.default');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || $mailer === '' || $mailer === 'log' || ! config('mail.mailers.'.$mailer)) {
            return;
        }

        $p = $digest->payload;
        $lines = ['Ringkasan harian POS Merchant Hunter - '.($p['date'] ?? '')];
        foreach (['new_open' => 'Baru buka', 'hot' => 'Prospek panas', 'follow_up' => 'Perlu follow-up'] as $key => $label) {
            $lines[] = '';
            $lines[] = $label.' ('.count($p[$key] ?? []).')';
            foreach ($p[$key] ?? [] as $row) {
                $lines[] = '- '.$row['name'].' (skor '.$row['score'].')';
            }
        }

        try {
            Mail::raw(implode("\n", $lines), fn ($m) => $m->to($to)->subject('Ringkasan harian prospek '.($p['date'] ?? '')));
            $digest->update(['emailed_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Digest e-mail failed: '.$e->getMessage());
        }
    }
}
