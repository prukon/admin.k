<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users\Concerns;

use App\Models\TrainerProfile;
use App\Models\User;

trait UserDeleteClearsEmailTestHelpers
{
    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    protected function uniqueEmail(string $prefix): string
    {
        return $prefix . '-' . uniqid('', true) . '@example.test';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Удаляемый',
            'lastname'   => 'Клиент',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    protected function makeTrainerProfile(array $userAttributes = []): TrainerProfile
    {
        $user = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('trainer'),
            'team_id'    => null,
            'name'       => 'Удаляемый',
            'lastname'   => 'Тренер',
        ], $userAttributes));

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeStaff(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'name'       => 'Удаляемый',
            'lastname'   => 'Админ',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentStorePayload(string $email): array
    {
        return [
            'name'       => 'Новый',
            'lastname'   => 'Клиент',
            'email'      => $email,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function trainerStorePayload(string $email): array
    {
        return [
            'name'       => 'Новый',
            'lastname'   => 'Тренер',
            'email'      => $email,
            'is_enabled' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function staffStorePayload(string $email): array
    {
        return [
            'name'       => 'Новый',
            'lastname'   => 'Админ',
            'email'      => $email,
            'is_enabled' => 1,
        ];
    }

    protected function assertEmailCleared(int $userId): void
    {
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNull(User::withTrashed()->whereKey($userId)->value('email'));
    }

    protected function assertEmailUnchanged(int $userId, string $email): void
    {
        $this->assertNotSoftDeleted('users', ['id' => $userId]);
        $this->assertSame($email, User::query()->whereKey($userId)->value('email'));
    }

    protected function assertLiveUserHasEmail(string $email, int $exceptUserId): void
    {
        $created = User::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertNotSame($exceptUserId, $created->id);
        $this->assertSame($this->partner->id, (int) $created->partner_id);
    }
}
