<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Report;

use App\Http\Requests\Admin\Report\SavePersistedReportFiltersRequest;
use App\Services\Reports\PersistedReportFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait SavesPersistedReportFilters
{
    protected function storePersistedReportFilters(SavePersistedReportFiltersRequest $request, string $tableKey): JsonResponse|RedirectResponse
    {
        $service = app(PersistedReportFilters::class);
        $userId = (int) $request->user()->id;

        if ($request->boolean('reset')) {
            $service->store($userId, $tableKey, $service->defaults($tableKey));
        } else {
            $service->store($userId, $tableKey, $request->filters());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to($this->persistedReportFiltersPageUrl($tableKey));
    }

    private function persistedReportFiltersPageUrl(string $tableKey): string
    {
        return match ($tableKey) {
            PersistedReportFilters::PAYMENTS => route('payments'),
            PersistedReportFilters::MONTHLY => route('reports.payments.monthly'),
            PersistedReportFilters::LTV => route('reports.ltv'),
            PersistedReportFilters::LTV_TEAMS => route('reports.ltv.teams'),
            PersistedReportFilters::LTV_LOCATIONS => route('reports.ltv.locations'),
            PersistedReportFilters::LTV_ADMINS => route('reports.ltv.admins'),
            PersistedReportFilters::DEBTS => route('debts'),
            default => route('payments'),
        };
    }
}
