<?php

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('trainers.view') ?? false;
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

        if ($this->has('team_ids')) {
            $ids = $this->input('team_ids');
            $ids = is_array($ids) ? $ids : [];
            $this->merge([
                'team_ids' => array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)),
            ]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return [
            'lastname' => ['required', 'string', 'max:25'],
            'name' => ['required', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'default_base_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'default_rate_per_training' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'avatar' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => [
                'integer',
                Rule::exists('teams', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'lastname' => 'фамилия',
            'name' => 'имя',
            'email' => 'email',
            'phone' => 'телефон',
            'password' => 'пароль',
            'description' => 'описание',
            'is_enabled' => 'активен',
            'sort_order' => 'порядок сортировки',
            'default_base_salary' => 'оклад по умолчанию',
            'default_rate_per_training' => 'ставка за тренировку',
            'avatar' => 'аватар',
            'team_ids' => 'группы',
            'team_ids.*' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'lastname.required' => 'Введите фамилию',
            'name.required' => 'Введите имя',
            'email.email' => 'Укажите корректный email',
            'email.unique' => 'Пользователь с таким email уже существует',
            'phone.unique' => 'Пользователь с таким телефоном уже существует',
            'password.min' => 'Пароль должен быть не короче :min символов',
            'avatar.max' => 'Аватар не должен превышать :max КБ',
            'avatar.mimetypes' => 'Аватар должен быть в формате JPEG, PNG или WebP',
            'team_ids.*.exists' => 'Выберите группы из списка',
            'default_base_salary.min' => 'Оклад не может быть отрицательным.',
            'default_rate_per_training.min' => 'Ставка не может быть отрицательной.',
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
