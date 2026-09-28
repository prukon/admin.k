<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\Users\StudentCardAccess;
use Illuminate\Foundation\Http\FormRequest;

class UserCardLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeUser = $this->route('user');
        if (! $routeUser instanceof User) {
            return false;
        }

        if (app(StudentCardAccess::class)->findCardUser($routeUser) === null) {
            abort(404);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'hide_authorizations' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'hide_authorizations' => 'скрыть входы',
        ];
    }

    public function messages(): array
    {
        return [
            'hide_authorizations.boolean' => 'Поле «Скрыть входы» должно быть да или нет.',
        ];
    }

    public function hideAuthorizations(): bool
    {
        if (! $this->exists('hide_authorizations')) {
            return true;
        }

        return $this->boolean('hide_authorizations');
    }
}
