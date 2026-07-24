<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserYearPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

        if (isset($payload['prices']) && is_array($payload['prices'])) {
            $payload['prices'] = array_map(static function ($row) {
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
            }, $payload['prices']);
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'prices' => ['required', 'array'],
            'prices.*.new_month' => ['required', 'date_format:Y-m-d'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'year' => 'год',
            'prices' => 'цены',
            'prices.*.new_month' => 'месяц',
            'prices.*.price' => 'цена',
            'prices.*.lesson_package_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу для сохранения цен.',
            'prices.*.lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
        ];
    }
}
