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
//                        Log::info('Найден тег для slug:', ['tag_id' => $userField->id]);

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





    public function delete($user)
    {
        $user->delete();
    }

}