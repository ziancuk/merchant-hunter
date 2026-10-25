<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\Actions\ReconcileProspectsAction;
use App\Models\Outlet;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Delegates to ReconcileProspectsAction (D-71); a failure never breaks the import or the manual resolve. */
final class ActionProspectReconciler implements ProspectReconciler
{
    public function __construct(private readonly ReconcileProspectsAction $action) {}

    public function reconcile(array $outletIds): int
    {
        if ($outletIds === []) {
            return 0;
        }

        try {
            return $this->action->execute(Outlet::query()->whereIn('id', $outletIds)->get());
        } catch (Throwable $e) {
            Log::warning('Prospect reconciliation after outlet import failed: '.$e->getMessage());

            return 0;
        }
    }
}
