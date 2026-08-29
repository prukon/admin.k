<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Support\SystemMonitors;
use Illuminate\Foundation\Http\FormRequest;

class ReverbStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(SystemMonitors::PERMISSION);
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
