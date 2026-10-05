<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Schedule\ScheduleJournalPageLength;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SaveScheduleJournalPageLengthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page_length' => ['required', 'integer', Rule::in(ScheduleJournalPageLength::LENGTHS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'page_length' => 'Показывать по',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page_length.required' => 'Укажите, сколько учеников показывать.',
            'page_length.integer' => 'Количество учеников должно быть целым числом.',
            'page_length.in' => 'Можно показать 20, 50 или 100 учеников.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! ($this->ajax() || $this->expectsJson())) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first() ?: 'Некорректные данные.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
