<?php

declare(strict_types=1);

namespace App\Http\Requests\Tables;

use App\Services\Tables\PersistedListFilters;
use Illuminate\Validation\Rule;

final class SaveContractsListFiltersRequest extends SavePersistedListFiltersRequest
{
    public function tableKey(): string
    {
        return PersistedListFilters::CONTRACTS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        $service = app(PersistedListFilters::class);

        return [
            'search_value' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'string', Rule::in($service->allowedTeamValues($this->user(), PersistedListFilters::CONTRACTS))],
            'status' => ['nullable', 'string', Rule::in(array_merge([''], PersistedListFilters::CONTRACT_STATUSES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldAttributes(): array
    {
        return [
            'search_value' => 'Поиск',
            'group_id' => 'Группа',
            'status' => 'Статус',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'search_value.max' => 'Поиск не длиннее 255 символов.',
            'group_id.in' => 'Выберите группу из списка.',
            'status.in' => 'Выберите статус договора из списка.',
        ];
    }
}
