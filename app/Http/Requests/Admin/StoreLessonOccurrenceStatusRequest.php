<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonOccurrenceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lessonPackages.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('icon') === '') {
            $this->merge(['icon' => null]);
        }
        if (! $this->has('consumes_lesson')) {
            $this->merge(['consumes_lesson' => false]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:191', Rule::in(config('lesson_occurrence_status_icons'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'consumes_lesson' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'color.required' => 'Укажите цвет',
            'color.regex' => 'Цвет в формате #RRGGBB',
            'icon.in' => 'Выберите иконку из списка',
        ];
    }
}
