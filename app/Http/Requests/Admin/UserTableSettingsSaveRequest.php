<?php

namespace App\Http\Requests\Admin;

use App\Models\UserTableSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserTableSettingsSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'columns' => [
                'required_without:page_length',
                'array',
                'min:1',
            ],
            'page_length' => [
                'sometimes',
                'integer',
                Rule::in(UserTableSetting::PAGE_LENGTHS),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'columns'     => 'Колонки',
            'page_length' => 'Показывать по',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'columns.required_without' => 'Передайте настройки колонок.',
            'columns.array'            => 'Настройки колонок должны быть массивом.',
            'columns.min'              => 'Передайте настройки колонок.',
            'page_length.integer'      => 'Количество строк должно быть целым числом.',
            'page_length.in'           => 'Можно показать 10, 20, 50 или 100 записей.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('columns');
        if (!is_array($raw)) {
            return;
        }

        $normalized = [];
        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $normalized[$key] = $bool ?? false;
        }

        $this->merge(['columns' => $normalized]);
    }
}
