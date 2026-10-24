<?php

declare(strict_types=1);

namespace App\Services\Master;

use Illuminate\Container\Attributes\Bind;

/** Seam over BE-Provider-Scheduler's ReconcileProspectsAction (D-71). */
#[Bind(ActionProspectReconciler::class)]
interface ProspectReconciler
{
    /**
     * Close existing prospects that match the given (newly imported/geocoded) outlets.
     *
     * @param  list<int>  $outletIds
     * @return int number of prospects closed
     */
    public function reconcile(array $outletIds): int;
}
