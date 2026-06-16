<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleStaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.role.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = $this->input('phone');
            $this->merge([
                'phone' => ($phone === null || trim((string) $phone) === '')
                    ? null
                    : $this->normalizeRuPhone($phone),
            ]);
        }

        if ($this->has('email') && is_string($this->input('email'))) {
            $email = trim($this->input('email'));
            $this->merge(['email' => $email !== '' ? $email : null]);
        }

        $password = $this->input('password');
        if ($password === null || trim((string) $password) === '') {
            $this->merge(['password' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'lastname' => ['required', 'string', 'max:25'],
            'name'     => ['required', 'string', 'max:25'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'avatar'   => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lastname' => 'фамилия',
            'name'     => 'имя',
            'email'    => 'email',
            'phone'    => 'телефон',
            'password' => 'пароль',
            'is_enabled' => 'активен',
            'avatar'   => 'аватар',
        ];
    }

    public function messages(): array
    {
        return [
            'lastname.required' => 'Введите фамилию',
            'name.required'     => 'Введите имя',
            'email.email'       => 'Укажите корректный email',
            'email.unique'      => 'Пользователь с таким email уже существует',
            'phone.unique'      => 'Пользователь с таким телефоном уже существует',
            'password.min'      => 'Пароль должен быть не короче :min символов',
            'avatar.max'        => 'Аватар не должен превышать :max КБ',
            'avatar.mimetypes'  => 'Аватар должен быть в формате JPEG, PNG или WebP',
        ];
    }

    private function normalizeRuPhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $d = preg_replace('/\D+/', '', $value);
        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7' . substr($d, 1);
        }
        if (strlen($d) === 10) {
            $d = '7' . $d;
        }

        return $d ? '+' . $d : null;
    }
}
