<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\SettingPricesMonth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ProlongMonthlyPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Превью всегда JSON, в том числе без AJAX: ошибка месяца → 422, не 302.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->routeIs('setting-prices.prolong-month.preview')) {
            throw new HttpResponseException(
                response()->json([
                    'message' => $validator->errors()->first() ?: 'Укажите корректный месяц.',
                    'errors' => $validator->errors()->toArray(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
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
            'selectedDate' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            if ($v->errors()->has('selectedDate')) {
                return;
            }

            $parsed = SettingPricesMonth::tryParseLabel((string) $this->input('selectedDate', ''));
            if ($parsed === null) {
                $v->errors()->add('selectedDate', 'Укажите корректный месяц.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'selectedDate' => 'месяц',
        ];
    }

    public function messages(): array
    {
        return [
            'selectedDate.required' => 'Укажите месяц.',
            'selectedDate.string' => 'Укажите корректный месяц.',
            'selectedDate.max' => 'Укажите корректный месяц.',
        ];
    }

    public function sourceMonthDate(): string
    {
        $parsed = SettingPricesMonth::tryParseLabel((string) $this->input('selectedDate', ''));
        if ($parsed === null) {
            throw new \LogicException('sourceMonthDate() called before validation.');
        }

        return $parsed;
    }
}
