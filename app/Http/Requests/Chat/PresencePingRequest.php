<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class PresencePingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
