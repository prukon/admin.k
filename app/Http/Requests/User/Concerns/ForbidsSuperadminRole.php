<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Role;

trait ForbidsSuperadminRole
{
    protected function forbidSuperadminRoleAssignment($validator): void
    {
        if (!$this->has('role_id')) {
            return;
        }

        if (Role::isSuperadminRoleId((int) $this->input('role_id'))) {
            $validator->errors()->add(
                'role_id',
                'Нельзя назначить роль «Суперадмин».'
            );
        }
    }
}
