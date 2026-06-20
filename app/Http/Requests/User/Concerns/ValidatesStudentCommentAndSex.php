<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\UserSex;
use App\Models\Role;
use Illuminate\Validation\Rule;

trait ValidatesStudentCommentAndSex
{
    protected function studentCommentRules(): array
    {
        if (!$this->user()?->can('users.comment') || !$this->isStudentRoleForCommentSex()) {
            return [];
        }

        return [
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function studentSexRules(): array
    {
        if (!$this->user()?->can('users.sex') || !$this->isStudentRoleForCommentSex()) {
            return [];
        }

        return [
            'sex' => ['nullable', 'string', Rule::in(array_column(UserSex::cases(), 'value'))],
        ];
    }

    protected function studentCommentAndSexRules(): array
    {
        return array_merge($this->studentCommentRules(), $this->studentSexRules());
    }

    protected function studentCommentAndSexAttributes(): array
    {
        return [
            'comment' => 'Комментарий',
            'sex' => 'Пол',
        ];
    }

    protected function studentCommentAndSexMessages(): array
    {
        return [
            'comment.string' => 'Поле «Комментарий» должно быть строкой.',
            'comment.max' => 'Поле «Комментарий» не должно превышать :max символов.',
            'sex.string' => 'Поле «Пол» должно быть строкой.',
            'sex.in' => 'Выберите корректное значение поля «Пол».',
        ];
    }

    protected function prepareStudentCommentAndSexForValidation(): void
    {
        if ($this->user()?->can('users.comment') && $this->isStudentRoleForCommentSex()) {
            if ($this->has('comment')) {
                $comment = trim((string) $this->input('comment'));
                $this->merge(['comment' => $comment !== '' ? $comment : null]);
            }
        } else {
            $this->offsetUnset('comment');
        }

        if ($this->user()?->can('users.sex') && $this->isStudentRoleForCommentSex()) {
            if ($this->has('sex') && $this->input('sex') === '') {
                $this->merge(['sex' => null]);
            }
        } else {
            $this->offsetUnset('sex');
        }
    }

    protected function isStudentRoleForCommentSex(): bool
    {
        $roleId = $this->effectiveRoleIdForCommentSexCheck();
        if (!$roleId) {
            return false;
        }

        return Role::query()->whereKey($roleId)->value('name') === 'user';
    }

    protected function effectiveRoleIdForCommentSexCheck(): ?int
    {
        $roleId = (int) ($this->input('role_id') ?? 0);

        return $roleId > 0 ? $roleId : null;
    }
}
