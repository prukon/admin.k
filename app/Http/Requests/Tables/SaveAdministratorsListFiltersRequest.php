<?php

declare(strict_types=1);

namespace App\Http\Requests\Tables;

use App\Services\Tables\PersistedListFilters;
use Illuminate\Validation\Rule;

final class SaveAdministratorsListFiltersRequest extends SavePersistedListFiltersRequest
{
    public function tableKey(): string
    {
        return PersistedListFilters::ADMINISTRATORS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['', 'active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldAttributes(): array
    {
        return [
            'name' => 'Имя',
            'status' => 'Статус',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'name.max' => 'Имя не длиннее 255 символов.',
            'status.in' => 'Выберите статус: все, только активные или только неактивные.',
        ];
    }
}
