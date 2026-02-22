<?php

namespace App\Http\Requests\Blog\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAiBlogPostActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('blog-view') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('prompt') && is_string($this->input('prompt'))) {
            $this->merge(['prompt' => trim((string) $this->input('prompt'))]);
        }
        if ($this->has('action') && is_string($this->input('action'))) {
            $this->merge(['action' => trim((string) $this->input('action'))]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['improve', 'seo', 'checklist', 'regenerate'])],
            'prompt' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => 'Действие',
            'prompt' => 'Доп. указания',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Выберите действие.',
            'action.in' => 'Некорректное действие.',
            'prompt.max' => 'Доп. указания слишком длинные (максимум :max символов).',
        ];
    }
}

