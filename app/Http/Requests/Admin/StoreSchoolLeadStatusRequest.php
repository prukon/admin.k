<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],
            'is_default_in_filter' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                 => 'Название',
            'color'                => 'Цвет',
            'sort_order'           => 'Сортировка',
            'is_default_in_filter' => 'Отображается заявки в этом статусе по умолчанию',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Укажите название статуса.',
            'name.max'             => 'Название не должно быть длиннее :max символов.',
            'color.regex'          => 'Укажите цвет в формате #RRGGBB.',
            'sort_order.integer'   => 'Сортировка должна быть целым числом.',
            'sort_order.min'       => 'Сортировка не может быть меньше :min.',
            'sort_order.max'       => 'Сортировка не может быть больше :max.',
            'is_default_in_filter.boolean' => 'Поле «Отображается заявки в этом статусе по умолчанию» должно быть да или нет.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_default_in_filter')) {
            $this->merge([
                'is_default_in_filter' => filter_var(
                    $this->input('is_default_in_filter'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }
}
