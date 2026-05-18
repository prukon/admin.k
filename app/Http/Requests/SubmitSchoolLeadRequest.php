<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubmitSchoolLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:50', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'consent_accepted' => ['required', 'accepted'],
            'recaptcha_token'  => ['required', 'string'],
            'utm_source'       => ['nullable', 'string', 'max:255'],
            'utm_medium'       => ['nullable', 'string', 'max:255'],
            'utm_campaign'     => ['nullable', 'string', 'max:255'],
            'utm_content'      => ['nullable', 'string', 'max:255'],
            'utm_term'         => ['nullable', 'string', 'max:255'],
            'page_url'         => ['nullable', 'string', 'max:2048'],
            'referrer'         => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'Укажите имя.',
            'name.max'                   => 'Имя слишком длинное.',
            'phone.required'             => 'Укажите телефон.',
            'phone.regex'                => 'Телефон может содержать только цифры, +, -, пробелы и скобки.',
            'phone.max'                  => 'Телефон слишком длинный.',
            'consent_accepted.required'  => 'Необходимо согласие на обработку персональных данных.',
            'consent_accepted.accepted'  => 'Необходимо согласие на обработку персональных данных.',
            'recaptcha_token.required'   => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('phone')) {
                $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));
                if (strlen($digits) < 6) {
                    $v->errors()->add('phone', 'Укажите корректный телефон (минимум 6 цифр).');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
