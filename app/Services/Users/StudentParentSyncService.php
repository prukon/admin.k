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
        'parent_passport',
        'parent_passport_issued',
        'parent_address',
        'parent_phone',
        'parent_email',
    ];

    /**
     * Ключи HTTP/form => ключ в filled_data договора / колонка parents (через applyParentProfileAttributes).
     *
     * @return array<string, string> requestKey => filledDataKey
     */
    public static function parentProfilePayloadKeys(): array
    {
        return [
            'parent_lastname'         => 'parent_lastname',
            'parent_firstname'        => 'parent_firstname',
            'parent_middlename'       => 'parent_middlename',
            'parent_passport'         => 'parent_passport',
            'parent_passport_issued'  => 'parent_passport_issued',
            'parent_address'          => 'parent_address',
            'parent_phone'            => 'parent_phone',
            'parent_email'            => 'parent_email',
        ];
    }

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
     * @param array<string, mixed> $payload
     */
    public function hasAccountParentProfilePayload(array $payload): bool
    {
        foreach (self::PARENT_PAYLOAD_KEYS as $key) {
            if ($key === 'parent_id') {
                continue;
            }
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
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
        $profileAttributes = $this->normalizeParentProfileAttributes($payload);

        if (array_key_exists('parent_id', $payload)) {
            $parentId = $this->normalizeParentId($payload['parent_id']);
        } elseif ($this->isAdminParentBlockCleared($payload, $names, $profileAttributes)) {
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

            $this->applyParentProfileAttributes($parent, $names, $profileAttributes);
            $parent->save();

            $user->parent_id = $parent->id;
            $user->save();

            return;
        }

        if ($names['has_any'] || $profileAttributes['has_any']) {
            $parent = ParentProfile::query()->create(array_merge(
                ['partner_id' => $partnerId],
                $this->buildParentAttributes($names, $profileAttributes),
            ));

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
     * @param array<string, mixed> $payload
     * @param array{has_any: bool} $names
     * @param array{has_any: bool} $profileAttributes
     */
    private function isAdminParentBlockCleared(array $payload, array $names, array $profileAttributes): bool
    {
        if ($names['has_any'] || $profileAttributes['has_any'] || array_key_exists('parent_id', $payload)) {
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

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     passport: ?string,
     *     passport_issued: ?string,
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     has_any: bool
     * }
     */
    private function normalizeParentProfileAttributes(array $payload): array
    {
        $passport = $this->normalizeTextPart($payload['parent_passport'] ?? null, 100);
        $passportIssued = $this->normalizeTextPart($payload['parent_passport_issued'] ?? null, 500);
        $address = $this->normalizeTextPart($payload['parent_address'] ?? null, 1000);
        $phone = $this->normalizePhonePart($payload['parent_phone'] ?? null);
        $email = $this->normalizeEmailPart($payload['parent_email'] ?? null);

        return [
            'passport'         => $passport,
            'passport_issued'  => $passportIssued,
            'address'          => $address,
            'phone'            => $phone,
            'email'            => $email,
            'has_any'          => $passport !== null
                || $passportIssued !== null
                || $address !== null
                || $phone !== null
                || $email !== null,
        ];
    }

    /**
     * @param array{lastname: ?string, firstname: ?string, middlename: ?string, has_any: bool} $names
     * @param array{passport: ?string, passport_issued: ?string, address: ?string, phone: ?string, email: ?string, has_any: bool} $profileAttributes
     */
    private function applyParentProfileAttributes(ParentProfile $parent, array $names, array $profileAttributes): void
    {
        if ($names['has_any']) {
            $parent->fill([
                'lastname'   => $names['lastname'],
                'firstname'  => $names['firstname'],
                'middlename' => $names['middlename'],
            ]);
        }

        if ($profileAttributes['has_any']) {
            $parent->fill(array_filter([
                'passport'        => $profileAttributes['passport'],
                'passport_issued' => $profileAttributes['passport_issued'],
                'address'         => $profileAttributes['address'],
                'phone'           => $profileAttributes['phone'],
                'email'           => $profileAttributes['email'],
            ], static fn ($value) => $value !== null));
        }
    }

    /**
     * @param array{lastname: ?string, firstname: ?string, middlename: ?string, has_any: bool} $names
     * @param array{passport: ?string, passport_issued: ?string, address: ?string, phone: ?string, email: ?string, has_any: bool} $profileAttributes
     * @return array<string, mixed>
     */
    private function buildParentAttributes(array $names, array $profileAttributes): array
    {
        return array_filter([
            'lastname'        => $names['lastname'],
            'firstname'       => $names['firstname'],
            'middlename'      => $names['middlename'],
            'passport'        => $profileAttributes['passport'],
            'passport_issued' => $profileAttributes['passport_issued'],
            'address'         => $profileAttributes['address'],
            'phone'           => $profileAttributes['phone'],
            'email'           => $profileAttributes['email'],
        ], static fn ($value) => $value !== null);
    }

    private function normalizeNamePart(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', $value));

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeTextPart(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/u', ' ', $value));
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    private function normalizeEmailPart(mixed $value): ?string
    {
        $email = $this->normalizeTextPart($value, 255);

        return $email !== null ? mb_strtolower($email) : null;
    }

    private function normalizePhonePart(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits[0] = '7';
        }

        return mb_substr($digits, 0, 20);
    }

    /**
     * Обновление данных родителя из личного кабинета или после заполнения договора.
     *
     * @param array<string, mixed> $payload
     */
    public function updateFromAccount(User $user, int $partnerId, array $payload): ?ParentProfile
    {
        $names = $this->normalizeParentNames($payload);
        $profileAttributes = $this->normalizeParentProfileAttributes($payload);
        $user->loadMissing('parentProfile');

        if (!$names['has_any'] && !$profileAttributes['has_any']) {
            return $user->parentProfile;
        }

        $profile = $user->parentProfile;

        if ($profile) {
            if ((int) $profile->partner_id !== $partnerId) {
                throw ValidationException::withMessages([
                    'parent_lastname' => ['Родитель недоступен для текущей организации.'],
                ]);
            }

            $this->applyParentProfileAttributes($profile, $names, $profileAttributes);
            $profile->save();
        } else {
            $user->loadMissing('role');

            $profile = ParentProfile::query()->create(array_merge(
                ['partner_id' => $partnerId],
                $this->buildParentAttributes($names, $profileAttributes),
            ));

            if ($user->role?->name === 'user') {
                $user->parent_id = $profile->id;
                $user->save();
            }
        }

        return $profile;
    }
}
