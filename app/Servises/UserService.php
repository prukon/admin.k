<?php

namespace App\Servises;

use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;




class UserService
{
    //  - Создание учетной записи юзера
    public function store($data)
    {
        return User::create($data);
    }


//    обновление данных пользователя
    public function update($user, $data)
    {
        $currentUser = auth()->user(); // или $request->user()


        try {
            // Проверяем, что данные пришли
            if (!empty($data['custom']) && is_array($data['custom'])) {
                \Log::info('Полученные кастомные поля:', $data['custom']);
            } else {
                \Log::warning('Данные custom отсутствуют или не являются массивом.');
            }

            // Исключаем 'custom' из основного массива
            $userData = array_diff_key($data, ['custom' => '']);

            // Обновляем основные поля пользователя
            $user->update($userData);

            // Проверяем наличие пользовательских полей
            // Предположим, что в $user->role_id хранится ID роли пользователя.
// В $userField->permissions_id хранится массив ID ролей, которые МОГУТ редактировать поле.

            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

//                    if ($userField) {
//                        // Массив ID ролей, которым разрешено редактировать поле
//                        $permissionsId = $userField->permissions_id ?? [];
//
//                        // Разрешено редактировать ТОЛЬКО если $user->role_id есть в $permissionsId
//                        $isEditable = in_array($currentUser->role_id, $permissionsId);
//
//                        \Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет'));
//
//                        if ($isEditable) {
//                            // Сохраняем/обновляем значение в user_field_values
//                            $updated = UserFieldValue::updateOrCreate(
//                                [
//                                    'user_id'  => $user->id,
//                                    'field_id' => $userField->id,
//                                ],
//                                ['value' => $value]
//                            );
//
//                            \Log::info("Обновлено поле {$slug} (ID: {$userField->id}) для пользователя {$user->id}: {$value}");
//                        } else {
//                            \Log::warning("Пользователь {$user->id} не может редактировать поле {$slug}");
//                        }
//                    } else {
//                        \Log::warning("Поле с slug {$slug} не найдено в базе.");
//                    }

                    if ($userField) {
                        // Получаем список ролей, которым разрешено редактировать это поле
                        $allowedRoleIds = DB::table('user_field_role')
                            ->where('user_field_id', $userField->id)
                            ->pluck('role_id')
                            ->toArray();

                        \Log::debug("UserField ID {$userField->id}, allowed roles:", $allowedRoleIds);

                        // Разрешено редактировать только если роль текущего пользователя есть в списке
                        $isEditable = in_array($currentUser->role_id, $allowedRoleIds);

                        \Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет'));

                        if ($isEditable) {
                            // Сохраняем/обновляем значение в user_field_values
                            $updated = UserFieldValue::updateOrCreate(
                                [
                                    'user_id'  => $user->id,
                                    'field_id' => $userField->id,
                                ],
                                ['value' => $value]
                            );

                            \Log::info("Обновлено поле {$slug} (ID: {$userField->id}) для пользователя {$user->id}: {$value}");
                        } else {
                            \Log::warning("Пользователь {$currentUser->id} не может редактировать поле {$slug}");
                        }
                    } else {
                        \Log::warning("Поле с slug {$slug} не найдено в базе.");
                    }



                }
            } else {
                \Log::warning('Поле custom отсутствует или не является массивом.');
            }





        } catch (\Exception $e) {
            \Log::error('Ошибка при обновлении пользователя:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function delete($user)
    {
        $user->delete();
    }

    public function updatePassword() {

    }

}