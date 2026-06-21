<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\Users\StudentParentSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly TrainerProfileSyncService $trainerProfileSync,
        private readonly TeamTrainerSyncService $teamTrainerSync,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly StudentParentSyncService $studentParentSync,
    ) {
    }

    //  - Создание учетной записи юзера
    public function store($data)
    {
        $teamIds = array_key_exists('team_ids', $data) ? (array) $data['team_ids'] : [];
        $parentPayload = $this->studentParentSync->extractParentPayload($data);
        $userData = $this->studentParentSync->stripParentPayload($data);
        unset($userData['team_ids'], $userData['team_id']);

        $user = User::create($userData);
        $user->load('role');
        $this->trainerProfileSync->syncForUser($user);

        $partnerId = (int) ($user->partner_id ?? 0);
        if ($partnerId > 0) {
            $this->studentParentSync->syncForStudent($user, $partnerId, $parentPayload);
        }

        if ($user->role?->name === 'user') {
            $this->teamUserSync->syncTeamsForStudent($user, $teamIds);
        }

        return $user->refresh();
    }

//    обновление данных пользователя
    public function update2 ($user, $data)
    {
        $currentUser = auth()->user();

        try {
            // Проверяем, что данные пришли
            if (!empty($data['custom']) && is_array($data['custom'])) {
                Log::info('Полученные кастомные поля:', $data['custom']);
            } else {
                Log::warning('Данные custom отсутствуют или не являются массивом.');
            }

            // Исключаем 'custom' из основного массива
            $userData = array_diff_key($data, ['custom' => '']);

            /**
             * ✅ Определяем "админскую роль" по roles.name = 'admin' (без хардкода ID).
             * Кешируем role_id -> name в пределах запроса.
             */
            $roleNameById = static function (?int $roleId): ?string {
                static $cache = [];
                if (!$roleId) return null;

                if (!array_key_exists($roleId, $cache)) {
                    $cache[$roleId] = \App\Models\Role::query()
                        ->whereKey($roleId)
                        ->value('name'); // string|null
                }
                return $cache[$roleId];
            };

            // --- жёсткая политика для админа при включённой глобалке ---
            try {
                $forceAdmin2fa = method_exists(Setting::class, 'getBool')
                    ? Setting::getBool('force_2fa_admins', false, null)
                    : (bool) DB::table('settings')
                        ->where('name', 'force_2fa_admins')
                        ->whereNull('partner_id')
                        ->value('status');

                $userRoleName = $roleNameById((int)($user->role_id ?? 0));
                $isAdminRole  = ($userRoleName === 'admin');

                if ($isAdminRole && $forceAdmin2fa) {
                    // Нельзя выключить 2FA у админа, если глобалка включена
                    $userData['two_factor_enabled'] = 1;

                    Log::info('UserService: force two_factor_enabled=1 for admin due to global setting', [
                        'user_id' => $user->id,
                        'role_name' => $userRoleName,
                    ]);
                } else {
                    // Нормализуем инпут чекбокса, чтобы 0/1 корректно приехали
                    if (array_key_exists('two_factor_enabled', $userData)) {
                        $userData['two_factor_enabled'] = (int)!!$userData['two_factor_enabled'];
                    }
                }
            } catch (\Throwable $e) {
                Log::error('UserService: failed to enforce admin 2FA policy', ['error' => $e->getMessage()]);
            }

            // Обновляем основные поля пользователя
            $user->update($userData);

            // --- обработка custom полей (как у тебя) ---
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        $allowedRoleIds = DB::table('user_field_role')
                            ->where('user_field_id', $userField->id)
                            ->pluck('role_id')
                            ->toArray();

                        Log::debug("UserField ID {$userField->id}, allowed roles:", $allowedRoleIds);

                        $isEditable = in_array($currentUser->role_id, $allowedRoleIds);

                        Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет'));

                        if ($isEditable) {
                            UserFieldValue::updateOrCreate(
                                [
                                    'user_id'  => $user->id,
                                    'field_id' => $userField->id,
                                ],
                                ['value' => $value]
                            );

                            Log::info("Обновлено поле {$slug} (ID: {$userField->id}) для пользователя {$user->id}: {$value}");
                        } else {
                            Log::warning("Пользователь {$currentUser->id} не может редактировать поле {$slug}");
                        }
                    } else {
                        Log::warning("Поле с slug {$slug} не найдено в базе.");
                    }
                }
            } else {
                Log::warning('Поле custom отсутствует или не является массивом.');
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении пользователя:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function update($user, $data)
    {
        $currentUser = auth()->user();

        try {
            if (!empty($data['custom']) && is_array($data['custom'])) {
                Log::info('Полученные кастомные поля:', $data['custom']);
            } else {
                Log::warning('Данные custom отсутствуют или не являются массивом.');
            }

            // Исключаем 'custom', родителя и группы из основного массива users
            $teamIds = array_key_exists('team_ids', $data) ? (array) $data['team_ids'] : null;
            $parentPayload = $this->studentParentSync->extractParentPayload($data);
            $userData = array_diff_key(
                $this->studentParentSync->stripParentPayload($data),
                ['custom' => '', 'team_ids' => '']
            );
            unset($userData['team_id']);

            /**
             * ✅ role_id -> roles.name (кеш в пределах запроса)
             */
            $roleNameById = static function (?int $roleId): ?string {
                static $cache = [];
                if (!$roleId) return null;

                if (!array_key_exists($roleId, $cache)) {
                    $cache[$roleId] = \App\Models\Role::query()
                        ->whereKey($roleId)
                        ->value('name');
                }
                return $cache[$roleId];
            };

            // --- жёсткая политика для админа при включённой глобалке ---
            try {
                $forceAdmin2fa = method_exists(Setting::class, 'getBool')
                    ? Setting::getBool('force_2fa_admins', false, null)
                    : (bool) DB::table('settings')
                        ->where('name','force_2fa_admins')
                        ->whereNull('partner_id')
                        ->value('status');

                $userRoleName = $roleNameById((int)($user->role_id ?? 0));
                $isAdminRole  = ($userRoleName === 'admin');

                if ($isAdminRole && $forceAdmin2fa) {
                    $userData['two_factor_enabled'] = 1;

                    Log::info('UserService: force two_factor_enabled=1 for admin due to global setting', [
                        'user_id' => $user->id,
                        'role_name' => $userRoleName,
                    ]);
                } else {
                    if (array_key_exists('two_factor_enabled', $userData)) {
                        $userData['two_factor_enabled'] = (int)!!$userData['two_factor_enabled'];
                    }
                }
            } catch (\Throwable $e) {
                Log::error('UserService: failed to enforce admin 2FA policy', ['error' => $e->getMessage()]);
            }

            $effectiveRoleId = (int) ($userData['role_id'] ?? $user->role_id ?? 0);
            if ($roleNameById($effectiveRoleId) === 'trainer') {
                unset($userData['team_id']);
            }

            // Обновляем основные поля пользователя
            $user->update($userData);
            $user->refresh();
            $user->load('role');
            $this->trainerProfileSync->syncForUser($user);

            if (
                $user->role?->name === 'user'
                && $teamIds !== null
                && $currentUser?->can('users.group.update')
            ) {
                $this->teamUserSync->syncTeamsForStudent($user, $teamIds);
            } elseif (
                $user->role?->name === 'trainer'
                && $teamIds !== null
                && $currentUser?->can('trainers.view')
            ) {
                $profile = TrainerProfile::query()
                    ->where('user_id', $user->id)
                    ->first();

                if ($profile) {
                    $this->teamTrainerSync->syncTeamsForTrainer($profile, $teamIds);
                }
            }

            // --- обработка custom полей (оставляем как было) ---
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        $allowedRoleIds = DB::table('user_field_role')
                            ->where('user_field_id', $userField->id)
                            ->pluck('role_id')
                            ->toArray();

                        Log::debug("UserField ID {$userField->id}, allowed roles:", $allowedRoleIds);

                        $isEditable = in_array($currentUser->role_id, $allowedRoleIds);

                        Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет'));

                        if ($isEditable) {
                            UserFieldValue::updateOrCreate(
                                [
                                    'user_id'  => $user->id,
                                    'field_id' => $userField->id,
                                ],
                                ['value' => $value]
                            );

                            Log::info("Обновлено поле {$slug} (ID: {$userField->id}) для пользователя {$user->id}: {$value}");
                        } else {
                            Log::warning("Пользователь {$currentUser->id} не может редактировать поле {$slug}");
                        }
                    } else {
                        Log::warning("Поле с slug {$slug} не найдено в базе.");
                    }
                }
            } else {
                Log::warning('Поле custom отсутствует или не является массивом.');
            }

            $partnerId = (int) ($user->partner_id ?? 0);
            $shouldSyncParent = $this->hasParentPayloadKeys($parentPayload)
                || $user->role?->name !== 'user';
            if ($partnerId > 0 && $shouldSyncParent) {
                $this->studentParentSync->syncForStudent($user, $partnerId, $parentPayload);
            }

        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении пользователя:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasParentPayloadKeys(array $payload): bool
    {
        return array_key_exists('parent_id', $payload)
            || array_key_exists('parent_lastname', $payload)
            || array_key_exists('parent_firstname', $payload)
            || array_key_exists('parent_middlename', $payload);
    }

    public function delete($user)
    {
        $user->delete();
    }

    public function updatePassword() {

    }

}