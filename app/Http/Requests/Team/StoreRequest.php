<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'weekdays' => 'nullable|array',
            'is_enabled' => 'boolean',
            'order_by' => 'nullable|integer', // Здесь мы указываем, что это может быть null
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'title.string' => 'Введите название',
        ];
    }

    /**
     * Modify the validated data before using it.
     */
 
}
