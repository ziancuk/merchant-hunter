<?php

declare(strict_types=1);

namespace App\Services\Master;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delegates to App\Actions\ReconcileProspectsAction (owned by BE-Provider-Scheduler) when it exists.
 * Its handle() is expected to accept the list of outlet ids; failures never break the import.
 */
final class ActionProspectReconciler implements ProspectReconciler
{
    private const ACTION = 'App\\Actions\\ReconcileProspectsAction';

    public function reconcile(array $outletIds): int
    {
        if ($outletIds === [] || ! class_exists(self::ACTION)) {
            return 0;
        }

        try {
            $result = app(self::ACTION)->handle($outletIds);

            return is_int($result) ? $result : 0;
        } catch (Throwable $e) {
            Log::warning('Prospect reconciliation after outlet import failed: '.$e->getMessage());

            return 0;
        }
    }
}
