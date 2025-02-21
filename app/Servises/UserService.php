<?php

namespace App\Servises;

use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\Log;




class UserService
{
    //  - Создание учетной записи юзера
    public function store($data)
    {
        return User::create($data);

    }

//  - Редактировние учетной записи юзера
//  - Редактировние учетной записи админа
//  - Редактировние юзера на старанице Юзеры
    public function updateold($user, $data)
    {
        try {

            // Исключаем поле 'custom' из данных для обновления пользователя
            $userData = array_diff_key($data, ['custom' => '']);

            //Обноляем поля в юзере
            $user->update($userData);


            // Проверка  на наличие пользовательских полей и обновляем их
            if (array_key_exists('custom', $data) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
//                    Log::info('Обработка custom поля:', ['slug' => $slug, 'value' => $value]);

                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {

                        // Сохраняем или обновляем значение пользовательского поля
                        UserFieldValue::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'field_id' => $userField->id,
                            ],
                            ['value' => $value]
                        );

//                        Log::info('Обновлено поле:', ['user_id' => $user->id, 'tag_id' => $tag->id, 'value' => $value]);
                    } else {
//                        Log::warning('Тег не найден для slug:', ['slug' => $slug]);
                    }
                }
            } else {
//                Log::warning('Поле custom отсутствует или не является массивом:', ['custom' => $data['custom'] ?? null]);
            }
        } catch (\Exception $e) {
//            Log::error('Ошибка при обновлении пользователя:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    public function update2($user, $data)
    {
        try {
            // Исключаем поле 'custom' из данных для обновления пользователя
            $userData = array_diff_key($data, ['custom' => '']);

            // Обновляем основные поля пользователя
            $user->update($userData);

            // Проверяем наличие пользовательских полей
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        // Проверяем, может ли пользователь редактировать это поле
                        $permissions = $userField->permissions ?? [];
                        $isEditable = empty($permissions) || in_array($user->role, $permissions);

                        if ($isEditable) {
                            // Сохраняем или обновляем значение пользовательского поля
                            UserFieldValue::updateOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'field_id' => $userField->id,
                                ],
                                ['value' => $value]
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }


    public function update($user, $data)
    {
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
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        // Проверяем, может ли пользователь редактировать это поле
                        $permissions = $userField->permissions ?? [];
                        $isEditable = empty($permissions) || in_array($user->role, $permissions);

                        \Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет'));

                        if ($isEditable) {
                            $updated = UserFieldValue::updateOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'field_id' => $userField->id,
                                ],
                                ['value' => $value]
                            );

                            \Log::info("Обновлено поле {$slug} с ID {$userField->id} для пользователя {$user->id}: {$value}");
                        } else {
                            \Log::warning("Пользователь {$user->id} не может редактировать поле {$slug}");
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




    //  - Редактировние юзера на старанице Юзеры
    public function delete($user)
    {
        $user->delete();
    }

}