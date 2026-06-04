<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateContractTemplateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_subject'   => ['nullable', 'string', 'max:255'],
            'email_body_html' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email_subject'   => 'Тема письма',
            'email_body_html' => 'Текст письма',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Ошибка валидации.',
                'errors'  => $validator->errors(),
            ], 422));
        }

        $template = $this->route('template');

        throw new HttpResponseException(
            redirect()
                ->route('contract-templates.index', ['email' => $template->id])
                ->withErrors($validator)
                ->withInput()
        );
    }
}
