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
        // Получаем ID пользователя из параметра маршрута
        $userId = $this->route('user')->id;

        return [
            'birthday' => 'nullable|date',
            'email' => 'required|string|email|max:20|unique:users,email,' . $userId,
        ];
    }

    public function attributes()
    {
        return [
            'birthday' => 'Дата рождения',
            'email' => 'Email',
        ];
    }
    public function messages()
    {
        return [

//            // Поле "Дата рождения"
            'birthday.date' => 'Поле "Дата рождения" должно быть корректной датой.',
            'birthday.before_or_equal' => 'Поле "Дата рождения" не может быть позднее сегодняшнего дня.',

            // Поле "Email"
            'email.required' => 'Поле "Email" обязательно для заполнения.',
            'email.string' => 'Поле "Email" должно быть строкой.',
            'email.email' => 'Поле "Email" должно быть действительным адресом электронной почты.',
            'email.max' => 'Поле "Email" не должно превышать :max символов.',
            'email.unique' => 'Этот email уже используется.',

        ];
    }
}