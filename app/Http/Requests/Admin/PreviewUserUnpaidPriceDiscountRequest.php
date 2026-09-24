<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewUserUnpaidPriceDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.discount.manage');
    }

    public function rules(): array
    {
        return [
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'discount_percent' => 'Скидка, %',
        ];
    }

    public function messages(): array
    {
        return [
            'discount_percent.required' => 'Укажите скидку, %.',
            'discount_percent.integer' => 'Поле «Скидка, %» должно быть целым числом.',
            'discount_percent.min' => 'Поле «Скидка, %» не может быть меньше :min.',
            'discount_percent.max' => 'Поле «Скидка, %» не может быть больше :max.',
        ];
    }
}
