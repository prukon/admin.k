<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'type' => 'required|string|in:group,individual',
            'default_duration_minutes' => 'nullable|integer|min:1|max:600',
            'weekdays' => 'nullable|array', // Правило 'array' указывает, что значение должно быть массивом
//            'description' => 'string',
//            'image' => '',
            'is_enabled' => 'boolean',
            'order_by' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'title.string' => 'Введите название',
            'type.required' => 'Укажите тип',
            'type.in' => 'Некорректный тип',
            'default_duration_minutes.integer' => 'Длительность должна быть числом (в минутах)',
            'default_duration_minutes.min' => 'Длительность должна быть больше 0 минут',
            'default_duration_minutes.max' => 'Длительность слишком большая',
        ];
    }
}
