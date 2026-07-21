<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderLessonOccurrenceStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lessonOccurrenceStatuses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Передайте порядок статусов',
        ];
    }
}
