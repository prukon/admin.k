<?php

namespace App\Http\Requests;

use App\Enums\SchoolLeadStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                'in:' . implode(',', SchoolLeadStatus::values()),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'   => 'Недопустимый статус.',
            'comment.max' => 'Комментарий слишком длинный.',
        ];
    }
}
