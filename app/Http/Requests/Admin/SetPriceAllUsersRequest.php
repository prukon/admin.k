<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetPriceAllUsersRequest extends FormRequest
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

        if (isset($payload['usersPrice']) && is_array($payload['usersPrice'])) {
            $payload['usersPrice'] = array_map(static function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                if (array_key_exists('lesson_package_id', $row)) {
                    $pkg = $row['lesson_package_id'];
                    if ($pkg === '' || $pkg === false) {
                        $row['lesson_package_id'] = null;
                    }
                }

                return $row;
            }, $payload['usersPrice']);
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'selectedDate' => ['required', 'string', 'max:255'],
            'teamId' => ['required', 'integer', 'min:1'],
            'usersPrice' => ['required', 'array'],
            'usersPrice.*.user_id' => ['required', 'integer', 'min:1'],
            'usersPrice.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'usersPrice.*.lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)
                ),
            ],
            'usersPrice.*.user' => ['nullable', 'array'],
            'usersPrice.*.user.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'selectedDate' => 'месяц',
            'teamId' => 'группа',
            'usersPrice' => 'цены учеников',
            'usersPrice.*.user_id' => 'ученик',
            'usersPrice.*.price' => 'цена',
            'usersPrice.*.lesson_package_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'selectedDate.required' => 'Укажите месяц для установки цен.',
            'teamId.required' => 'Укажите группу.',
            'usersPrice.required' => 'Некорректные данные: список цен обязателен.',
            'usersPrice.array' => 'Некорректные данные: список цен должен быть массивом.',
            'usersPrice.*.user_id.required' => 'Не указан ученик в строке цены.',
            'usersPrice.*.price.required' => 'Укажите цену для ученика.',
            'usersPrice.*.price.numeric' => 'Цена должна быть числом.',
            'usersPrice.*.price.min' => 'Цена не может быть отрицательной.',
            'usersPrice.*.lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
        ];
    }
}
