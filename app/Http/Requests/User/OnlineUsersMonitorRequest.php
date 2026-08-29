<?php

namespace App\Http\Requests\User;

use App\Support\SystemMonitors;
use Illuminate\Foundation\Http\FormRequest;

class OnlineUsersMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(SystemMonitors::PERMISSION);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
