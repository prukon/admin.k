<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class GetTeamPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Read-only JSON: ошибки полей → 422, в том числе без AJAX (не пустой 200 и не 500).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first() ?: 'Проверьте данные.',
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
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
            'teamId' => ['required'],
            'selectedDate' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'teamId' => 'группа',
            'selectedDate' => 'месяц',
        ];
    }

    public function messages(): array
    {
        return [
            'teamId.required' => 'Укажите группу.',
            'selectedDate.required' => 'Укажите месяц.',
            'selectedDate.string' => 'Укажите корректный месяц.',
            'selectedDate.max' => 'Укажите корректный месяц.',
        ];
    }
}
