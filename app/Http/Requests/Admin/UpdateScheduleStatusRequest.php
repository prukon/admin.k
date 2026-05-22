<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'icon' => 'иконка',
            'color' => 'цвет',
            'sort_order' => 'сортировка',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название статуса.',
            'sort_order.required' => 'Укажите порядок сортировки.',
            'sort_order.integer' => 'Сортировка должна быть целым числом.',
            'sort_order.min' => 'Сортировка не может быть меньше :min.',
            'sort_order.max' => 'Сортировка не может быть больше :max.',
        ];
    }
}
