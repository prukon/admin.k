<?php

namespace App\Servises;

use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function store($data)
    {
//        User::create($data);
        return User::create($data);

    }

    public function update($user, $data)
    {
        try {

            // Исключаем поле 'custom' из данных для обновления пользователя
            $userData = array_diff_key($data, ['custom' => '']);
            $user->update($userData);


            // Проверка на наличие пользовательских полей
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
            $user->update($userData);

            // Логируем старые значения полей 'custom'
            $oldCustomData = UserFieldValue::where('user_id', $user->id)->get()->keyBy('field_id')->toArray();

            // Проверка на наличие пользовательских полей
            if (array_key_exists('custom', $data) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
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

                        // Логируем изменение значения поля 'custom'
                        $oldValue = $oldCustomData[$userField->id]['value'] ?? null;
                        if ($oldValue !== $value) {
                            Log::create([
                                'type' => 2, // Лог для обновления данных
                                'action' => 22, // Лог для обновления учетной записи
                                'author_id' => auth()->id(),
                                'description' => sprintf(
                                    "Изменение поля custom:\nSlug: %s\nСтарое значение: %s\nНовое значение: %s",
                                    $slug,
                                    $oldValue ?? '-',
                                    $value
                                ),
                                'created_at' => now(),
                            ]);
                        }
                    } else {
                        Log::warning('Тег не найден для slug:', ['slug' => $slug]);
                    }
                }
            } else {
                Log::warning('Поле custom отсутствует или не является массивом:', ['custom' => $data['custom'] ?? null]);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }




    public function delete($user)
    {
        $user->delete();
    }

}