<?php

namespace App\Http\Requests\Blog\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateAiBlogImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('blog-view') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('prompt_extra') && is_string($this->input('prompt_extra'))) {
            $this->merge(['prompt_extra' => trim((string) $this->input('prompt_extra'))]);
        }
    }

    public function rules(): array
    {
        return [
            'prompt_extra' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'prompt_extra' => 'Доп. указания',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt_extra.max' => 'Доп. указания слишком длинные (максимум :max символов).',
        ];
    }
}

