<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Tables\SavePersistedListFiltersRequest;
use App\Services\Tables\PersistedListFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait SavesPersistedListFilters
{
    protected function storePersistedListFilters(SavePersistedListFiltersRequest $request): JsonResponse|RedirectResponse
    {
        app(PersistedListFilters::class)->store(
            (int) $request->user()->id,
            $request->tableKey(),
            $request->filters()
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to($this->persistedListFiltersPageUrl($request->tableKey()));
    }

    private function persistedListFiltersPageUrl(string $tableKey): string
    {
        return match ($tableKey) {
            PersistedListFilters::USERS => route('admin.user1'),
            PersistedListFilters::TRAINERS => route('admin.trainers.index'),
            PersistedListFilters::ADMINISTRATORS => route('admin.administrators.index'),
            PersistedListFilters::SCHOOL_LEADS => route('admin.school-leads'),
            PersistedListFilters::CONTRACTS => route('contracts.index'),
            default => url()->previous(),
        };
    }
}
