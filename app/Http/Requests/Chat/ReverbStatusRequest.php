<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

class ReverbStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PartnerContext::class)->isSuperAdmin($user);
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
