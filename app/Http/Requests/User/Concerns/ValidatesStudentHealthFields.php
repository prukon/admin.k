<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Role;

trait ValidatesStudentHealthFields
{
    private const STUDENT_HEALTH_FIELDS = [
        'is_individual_traits',
        'is_on_medical_register',
        'is_with_disability',
    ];

    protected function studentHealthFieldRules(): array
    {
        if (!$this->user()?->can('users.other.update') || !$this->isStudentRoleEffective()) {
            return [];
        }

        $rules = [];
        foreach (self::STUDENT_HEALTH_FIELDS as $field) {
            $rules[$field] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    protected function studentHealthFieldAttributes(): array
    {
        return [
            'is_individual_traits'   => 'Индивидуальные особенности воспитанника',
            'is_on_medical_register' => 'Учёт у медицинских специалистов',
            'is_with_disability'     => 'Наличие инвалидности',
        ];
    }

    protected function studentHealthFieldMessages(): array
    {
        return [
            'is_individual_traits.boolean'   => 'Некорректное значение поля «Индивидуальные особенности воспитанника».',
            'is_on_medical_register.boolean' => 'Некорректное значение поля «Учёт у медицинских специалистов».',
            'is_with_disability.boolean'     => 'Некорректное значение поля «Наличие инвалидности».',
        ];
    }

    protected function prepareStudentHealthFieldsForValidation(): void
    {
        if (!$this->user()?->can('users.other.update') || !$this->isStudentRoleEffective()) {
            foreach (self::STUDENT_HEALTH_FIELDS as $field) {
                $this->offsetUnset($field);
            }

            return;
        }

        foreach (self::STUDENT_HEALTH_FIELDS as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            if ($value === '' || $value === null) {
                $this->merge([$field => null]);

                continue;
            }

            if (!in_array((string) $value, ['0', '1'], true)) {
                continue;
            }

            $this->merge([$field => $value === '1' || $value === 1 || $value === true]);
        }
    }

    protected function isStudentRoleEffective(): bool
    {
        $roleId = $this->effectiveRoleIdForStudentHealthCheck();

        if (!$roleId) {
            return false;
        }

        return Role::query()->whereKey($roleId)->value('name') === 'user';
    }

    protected function effectiveRoleIdForStudentHealthCheck(): ?int
    {
        $roleId = (int) ($this->input('role_id') ?? 0);

        return $roleId > 0 ? $roleId : null;
    }
}
