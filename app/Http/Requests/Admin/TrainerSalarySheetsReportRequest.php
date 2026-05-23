<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class TrainerSalarySheetsReportRequest extends TrainerSalaryReportRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'latest_only' => ['nullable', 'boolean'],
        ]);
    }

    public function latestOnly(): bool
    {
        return $this->boolean('latest_only', false);
    }
}
