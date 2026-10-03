<?php

declare(strict_types=1);

namespace App\Http\Requests\Tables;

use App\Services\Tables\PersistedListFilters;
use Illuminate\Foundation\Http\FormRequest;

abstract class SavePersistedListFiltersRequest extends FormRequest
{
    abstract public function tableKey(): string;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->boolean('reset')) {
            return [
                'reset' => ['required', 'boolean'],
            ];
        }

        return array_merge(
            ['reset' => ['sometimes', 'boolean']],
            $this->fieldRules()
        );
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function fieldRules(): array;

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge([
            'reset' => 'Сброс',
        ], $this->fieldAttributes());
    }

    /**
     * @return array<string, string>
     */
    abstract protected function fieldAttributes(): array;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'reset.required' => 'Не удалось сбросить фильтры.',
            'reset.boolean' => 'Не удалось сбросить фильтры.',
        ], $this->fieldMessages());
    }

    /**
     * @return array<string, string>
     */
    abstract protected function fieldMessages(): array;

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $service = app(PersistedListFilters::class);
        if ($this->boolean('reset')) {
            return $service->defaults($this->user(), $this->tableKey());
        }

        return $service->normalize($this->validated(), $this->tableKey(), $this->user());
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('reset')) {
            $this->merge(['reset' => true]);

            return;
        }

        $this->merge(['reset' => false]);
    }
}
