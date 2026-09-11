<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ClearFormerMemberMonthChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();
        if ($payload === [] && $this->getContent() !== '') {
            $decoded = json_decode($this->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'selectedDate' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'selectedDate' => 'месяц',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Не указан ученик.',
            'user_id.integer' => 'Некорректный ученик.',
            'user_id.min' => 'Некорректный ученик.',
            'team_id.required' => 'Выберите группу.',
            'team_id.integer' => 'Некорректная группа.',
            'team_id.min' => 'Некорректная группа.',
            'selectedDate.required' => 'Укажите месяц.',
            'selectedDate.string' => 'Укажите корректный месяц.',
            'selectedDate.max' => 'Укажите корректный месяц.',
        ];
    }
}
