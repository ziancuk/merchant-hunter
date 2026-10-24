<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Http\Requests\OutletIndexRequest;
use App\Http\Requests\PipelineCardsRequest;
use App\Repositories\DashboardQuery;
use App\Repositories\OutletRepository;
use App\Repositories\PageProps;
use App\Repositories\PipelineQuery;
use App\Services\Settings\SettingsService;
use Inertia\Inertia;
use Inertia\Response;

/** Inertia pages (CONTRACTS §1); each returns its props only. */
class PagesController extends Controller
{
    public function __construct(private readonly PageProps $props) {}

    public function map(): Response
    {
        return Inertia::render('Map/Index', $this->props->map());
    }

    public function pipeline(PipelineCardsRequest $request, PipelineQuery $query, SettingsService $settings): Response
    {
        $view = $request->query('view') === 'table' ? 'table' : 'kanban';

        return Inertia::render('Pipeline/Index', [
            'view' => $view,
            'columns' => $query->columns($request->filter(), (int) ($request->validated()['per_status'] ?? 50)),
            'filter_options' => $this->props->filterOptions(),
            'rejection_reasons' => $this->props->rejectionReasons(),
            'contact_rules' => $settings->contactRules(),
        ]);
    }

    public function dashboard(DashboardQuery $dashboard): Response
    {
        return Inertia::render('Dashboard/Index', $dashboard->all());
    }

    public function impact(): Response
    {
        return Inertia::render('Impact/Index', $this->props->impact());
    }

    public function dataSources(): Response
    {
        return Inertia::render('Master/DataSources', ['sources' => $this->props->dataSources()]);
    }

    public function outlets(OutletIndexRequest $request): Response
    {
        return Inertia::render('Master/Outlets', $this->props->masterOutlets($request->validated()));
    }

    public function outletReview(OutletRepository $outlets): Response
    {
        return Inertia::render('Master/OutletReview', ['queue' => $outlets->reviewQueue()]);
    }

    public function categories(): Response
    {
        return Inertia::render('Master/Categories', ['categories' => $this->props->categories()]);
    }

    public function scheduler(DashboardQuery $dashboard): Response
    {
        return Inertia::render('Scheduler/Runs', [
            'runs' => $this->props->schedulerRuns(),
            'health' => $dashboard->schedulerHealth(),
        ]);
    }

    public function settings(SettingsService $settings): Response
    {
        return Inertia::render('Master/Settings', ['settings' => $settings->structured()]);
    }
}
