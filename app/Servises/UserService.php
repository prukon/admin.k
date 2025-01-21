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
    public function update($user, $data)
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

    //  - Редактировние юзера на старанице Юзеры
    public function delete($user)
    {
        $user->delete();
    }

}