<?php

namespace App\Http\Requests\User;

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
//            'name' => 'required|string|max:30',
            'birthday' => 'nullable|date',
//            'team_id' => 'nullable|string',
//            'start_date' => 'nullable|date',
//            'image' => '',
            'email' => 'required|string|email|max:20',  // Поле email обязательно, должно быть строкой, соответствовать формату email и не превышать 255 символов
//            'is_enabled' => 'boolean',  // Поле is_enabled должно быть булевым значением
        ];
    }
}
