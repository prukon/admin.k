<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetManualUserPricePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required', 'integer', 'min:1'],
            'selectedDate' => ['required', 'string', 'max:255'],
            'mode'         => ['required', Rule::in(['paid', 'unpaid'])],
            'comment'      => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Укажите комментарий к ручному изменению.',
            'comment.min'      => 'Комментарий должен содержать не менее :min символов.',
            'comment.max'      => 'Комментарий слишком длинный.',
            'mode.in'          => 'Некорректный режим ручной отметки оплаты.',
        ];
    }
}
