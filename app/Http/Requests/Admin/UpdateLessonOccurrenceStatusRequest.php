<?php

namespace App\Http\Requests\Admin;

use App\Models\LessonOccurrenceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonOccurrenceStatusRequest extends FormRequest
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
    }

    public function rules(): array
    {
        /** @var LessonOccurrenceStatus|null $status */
        $status = $this->route('lessonOccurrenceStatus');

        $base = [
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:191', Rule::in(config('lesson_occurrence_status_icons'))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'consumes_lesson' => ['required', 'boolean'],
        ];

        if ($status instanceof LessonOccurrenceStatus && $status->is_system) {
            return array_merge([
                'title' => ['prohibited'],
            ], $base);
        }

        return array_merge([
            'title' => ['required', 'string', 'max:255'],
        ], $base);
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'title.prohibited' => 'Название системного статуса менять нельзя',
            'color.required' => 'Укажите цвет',
            'color.regex' => 'Цвет в формате #RRGGBB',
            'sort_order.required' => 'Укажите порядок сортировки',
            'icon.in' => 'Выберите иконку из списка',
        ];
    }
}
