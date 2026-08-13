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
        return array_merge(
            $this->studentCommentRules(),
            $this->studentSexRules(),
            $this->studentDiscountRules(),
        );
    }

    protected function studentDiscountRules(): array
    {
        if (!$this->user()?->can('users.discount.manage') || !$this->isStudentRoleForCommentSex()) {
            return [];
        }

        $percent = $this->normalizedDiscountPercentInput();
        $commentRules = ['nullable', 'string', 'max:500'];
        if ($percent >= 1) {
            $commentRules = ['required', 'string', 'max:500'];
        }

        return [
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_comment' => $commentRules,
        ];
    }

    protected function studentCommentAndSexAttributes(): array
    {
        return [
            'comment' => 'Комментарий',
            'sex' => 'Пол',
            'discount_percent' => 'Скидка, %',
            'discount_comment' => 'Основание скидки',
        ];
    }

    protected function studentCommentAndSexMessages(): array
    {
        return [
            'comment.string' => 'Поле «Комментарий» должно быть строкой.',
            'comment.max' => 'Поле «Комментарий» не должно превышать :max символов.',
            'sex.string' => 'Поле «Пол» должно быть строкой.',
            'sex.in' => 'Выберите корректное значение поля «Пол».',
            'discount_percent.integer' => 'Поле «Скидка, %» должно быть целым числом.',
            'discount_percent.min' => 'Поле «Скидка, %» не может быть меньше :min.',
            'discount_percent.max' => 'Поле «Скидка, %» не может быть больше :max.',
            'discount_comment.required' => 'Укажите основание скидки.',
            'discount_comment.string' => 'Поле «Основание скидки» должно быть строкой.',
            'discount_comment.max' => 'Поле «Основание скидки» не должно превышать :max символов.',
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

        $this->prepareStudentDiscountForValidation();
    }

    protected function prepareStudentDiscountForValidation(): void
    {
        if (!$this->user()?->can('users.discount.manage') || !$this->isStudentRoleForCommentSex()) {
            $this->offsetUnset('discount_percent');
            $this->offsetUnset('discount_comment');

            return;
        }

        if ($this->has('discount_percent')) {
            $raw = $this->input('discount_percent');
            if ($raw === '' || $raw === null) {
                $this->merge(['discount_percent' => null]);
            }
        }

        if ($this->has('discount_comment')) {
            $comment = trim((string) $this->input('discount_comment'));
            $this->merge(['discount_comment' => $comment !== '' ? $comment : null]);
        }

        // 0 / пусто → снять скидку. Отрицательные и дробные не затираем — их ловят rules.
        if ($this->shouldClearDiscountBecauseZeroOrEmpty()) {
            $this->merge([
                'discount_percent' => null,
                'discount_comment' => null,
            ]);
        }
    }

    protected function shouldClearDiscountBecauseZeroOrEmpty(): bool
    {
        $raw = $this->input('discount_percent');
        if ($raw === null || $raw === '') {
            return true;
        }
        if (! is_numeric($raw)) {
            return false;
        }
        if ((float) $raw < 0) {
            return false;
        }
        if ((float) $raw != floor((float) $raw)) {
            return false;
        }

        return (int) $raw < 1;
    }

    protected function normalizedDiscountPercentInput(): int
    {
        $raw = $this->input('discount_percent');
        if ($raw === null || $raw === '') {
            return 0;
        }
        if (! is_numeric($raw)) {
            return 0;
        }

        return (int) $raw;
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
