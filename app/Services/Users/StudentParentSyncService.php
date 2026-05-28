<?php

namespace App\Services\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StudentParentSyncService
{
    public const PARENT_PAYLOAD_KEYS = [
        'parent_id',
        'parent_lastname',
        'parent_firstname',
        'parent_middlename',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function hasAccountParentNamePayload(array $payload): bool
    {
        return array_key_exists('parent_lastname', $payload)
            || array_key_exists('parent_firstname', $payload)
            || array_key_exists('parent_middlename', $payload);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function extractParentPayload(array $data): array
    {
        $payload = [];

        foreach (self::PARENT_PAYLOAD_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function stripParentPayload(array $data): array
    {
        return array_diff_key($data, array_flip(self::PARENT_PAYLOAD_KEYS));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function syncForStudent(User $user, int $partnerId, array $payload): void
    {
        $user->loadMissing('role');

        if ($user->role?->name !== 'user') {
            $this->clearStudentParent($user);

            return;
        }

        $names = $this->normalizeParentNames($payload);

        if (array_key_exists('parent_id', $payload)) {
            $parentId = $this->normalizeParentId($payload['parent_id']);
        } elseif ($this->isAdminParentBlockCleared($payload, $names)) {
            $parentId = null;
        } elseif ($user->parent_id) {
            $parentId = (int) $user->parent_id;
        } else {
            $parentId = null;
        }

        if ($parentId !== null) {
            $parent = ParentProfile::query()
                ->where('partner_id', $partnerId)
                ->whereKey($parentId)
                ->first();

            if (!$parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Выбранный родитель не найден или недоступен.'],
                ]);
            }

            if ($names['has_any']) {
                $parent->fill([
                    'lastname'   => $names['lastname'],
                    'firstname'  => $names['firstname'],
                    'middlename' => $names['middlename'],
                ]);
                $parent->save();
            }

            $user->parent_id = $parent->id;
            $user->save();

            return;
        }

        if ($names['has_any']) {
            $parent = ParentProfile::query()->create([
                'partner_id' => $partnerId,
                'lastname'   => $names['lastname'],
                'firstname'  => $names['firstname'],
                'middlename' => $names['middlename'],
            ]);

            $user->parent_id = $parent->id;
            $user->save();

            return;
        }

        $this->clearStudentParent($user);
    }

    private function clearStudentParent(User $user): void
    {
        $user->parent_id = null;
        $user->save();
    }

    /**
     * Форма админки: все поля родителя пришли пустыми, parent_id не передан (очистка Select2).
     *
     * @param array<string, mixed> $payload
     * @param array{has_any: bool} $names
     */
    private function isAdminParentBlockCleared(array $payload, array $names): bool
    {
        if ($names['has_any'] || array_key_exists('parent_id', $payload)) {
            return false;
        }

        return array_key_exists('parent_lastname', $payload)
            && array_key_exists('parent_firstname', $payload)
            && array_key_exists('parent_middlename', $payload);
    }

    private function normalizeParentId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     lastname: ?string,
     *     firstname: ?string,
     *     middlename: ?string,
     *     has_any: bool
     * }
     */
    private function normalizeParentNames(array $payload): array
    {
        $lastname = $this->normalizeNamePart($payload['parent_lastname'] ?? null);
        $firstname = $this->normalizeNamePart($payload['parent_firstname'] ?? null);
        $middlename = $this->normalizeNamePart($payload['parent_middlename'] ?? null);

        return [
            'lastname'   => $lastname,
            'firstname'  => $firstname,
            'middlename' => $middlename,
            'has_any'    => $lastname !== null || $firstname !== null || $middlename !== null,
        ];
    }

    private function normalizeNamePart(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', $value));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Обновление данных родителя из личного кабинета (account-settings).
     *
     * @param array<string, mixed> $payload parent_lastname|firstname|middlename
     */
    public function updateFromAccount(User $user, int $partnerId, array $payload): ?ParentProfile
    {
        $names = $this->normalizeParentNames($payload);
        $user->loadMissing('parentProfile');

        if (!$names['has_any']) {
            return $user->parentProfile;
        }

        $profile = $user->parentProfile;

        if ($profile) {
            if ((int) $profile->partner_id !== $partnerId) {
                throw ValidationException::withMessages([
                    'parent_lastname' => ['Родитель недоступен для текущей организации.'],
                ]);
            }

            $profile->fill([
                'lastname'   => $names['lastname'],
                'firstname'  => $names['firstname'],
                'middlename' => $names['middlename'],
            ]);
            $profile->save();
        } else {
            $user->loadMissing('role');

            $profile = ParentProfile::query()->create([
                'partner_id' => $partnerId,
                'lastname'   => $names['lastname'],
                'firstname'  => $names['firstname'],
                'middlename' => $names['middlename'],
            ]);

            if ($user->role?->name === 'user') {
                $user->parent_id = $profile->id;
                $user->save();
            }
        }

        return $profile;
    }
}
