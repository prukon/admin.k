<?php

declare(strict_types=1);

namespace App\Http\Requests\Tables;

use App\Services\Tables\PersistedListFilters;
use Illuminate\Validation\Rule;

final class SaveUsersListFiltersRequest extends SavePersistedListFiltersRequest
{
    public function tableKey(): string
    {
        return PersistedListFilters::USERS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        $service = app(PersistedListFilters::class);
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'string', Rule::in($service->allowedTeamValues($this->user(), PersistedListFilters::USERS))],
            'status' => ['nullable', 'string', Rule::in(['', 'active', 'inactive'])],
        ];

        if ($this->user()?->can('contracts.view')) {
            $rules['contract'] = ['nullable', 'string', Rule::in(['', 'with', 'without', 'signed', 'unsigned'])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldAttributes(): array
    {
        return [
            'name' => 'Имя',
            'team_id' => 'Группа',
            'status' => 'Статус',
            'contract' => 'Договор',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'name.max' => 'Имя не длиннее 255 символов.',
            'team_id.in' => 'Выберите группу из списка.',
            'status.in' => 'Выберите статус: все, только активные или только неактивные.',
            'contract.in' => 'Выберите значение фильтра «Договор».',
        ];
    }
}
