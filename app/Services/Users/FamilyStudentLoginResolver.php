<?php

namespace App\Services\Users;

use App\Models\User;

/**
 * Логин семьи учеников: users.email первого ребёнка = parent_email;
 * следующие дети с тем же parent_id получают users.email = null и входят через семейный кабинет.
 */
final class FamilyStudentLoginResolver
{
    public const MODE_FIRST = 'first';

    public const MODE_SIBLING = 'sibling';

    /**
     * Нужно ли назначать логин из parent_email (лид или welcome без явного email ученика).
     */
    public function shouldApplyFromParentEmail(
        bool $fromSchoolLead,
        bool $sendWelcomeEmail,
        ?string $explicitEmail,
        ?string $parentEmail,
    ): bool {
        $parentEmail = trim((string) ($parentEmail ?? ''));
        if ($parentEmail === '') {
            return false;
        }

        if ($fromSchoolLead) {
            return true;
        }

        return $sendWelcomeEmail && trim((string) ($explicitEmail ?? '')) === '';
    }

    /**
     * @return array{
     *     mode: self::MODE_FIRST|self::MODE_SIBLING,
     *     user_email: string|null,
     *     notify_email: string,
     *     family_login_user: User|null,
     *     error_field: string|null,
     *     error_message: string|null,
     * }
     */
    public function resolve(int $partnerId, string $parentEmail, ?int $parentId): array
    {
        $parentEmail = trim($parentEmail);
        $parentId = $parentId !== null && $parentId > 0 ? $parentId : null;

        $holder = User::query()
            ->with('role')
            ->where('email', $parentEmail)
            ->first();

        if (!$holder) {
            return [
                'mode'               => self::MODE_FIRST,
                'user_email'         => $parentEmail,
                'notify_email'       => $parentEmail,
                'family_login_user'  => null,
                'error_field'        => null,
                'error_message'      => null,
            ];
        }

        if ($this->isCompatibleFamilyLoginHolder($holder, $partnerId, $parentId)) {
            return [
                'mode'               => self::MODE_SIBLING,
                'user_email'         => null,
                'notify_email'       => $parentEmail,
                'family_login_user'  => $holder,
                'error_field'        => null,
                'error_message'      => null,
            ];
        }

        return [
            'mode'               => self::MODE_FIRST,
            'user_email'         => null,
            'notify_email'       => $parentEmail,
            'family_login_user'  => null,
            'error_field'        => 'parent_email',
            'error_message'      => 'Этот адрес электронной почты уже зарегистрирован.',
        ];
    }

    private function isCompatibleFamilyLoginHolder(User $holder, int $partnerId, ?int $parentId): bool
    {
        if ((int) $holder->partner_id !== $partnerId) {
            return false;
        }

        if ($holder->role?->name !== 'user') {
            return false;
        }

        if ($parentId === null) {
            return false;
        }

        return (int) $holder->parent_id === $parentId;
    }
}
