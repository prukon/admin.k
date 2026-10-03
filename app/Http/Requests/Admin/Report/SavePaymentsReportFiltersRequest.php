<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Services\Reports\PersistedReportFilters;

final class SavePaymentsReportFiltersRequest extends SavePersistedReportFiltersRequest
{
    protected function filterProfile(): string
    {
        return PersistedReportFilters::PAYMENTS;
    }
}
