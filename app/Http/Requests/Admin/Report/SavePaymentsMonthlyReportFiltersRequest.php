<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Services\Reports\PersistedReportFilters;

final class SavePaymentsMonthlyReportFiltersRequest extends SavePersistedReportFiltersRequest
{
    protected function filterProfile(): string
    {
        return PersistedReportFilters::MONTHLY;
    }
}
