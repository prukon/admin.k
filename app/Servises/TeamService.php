<?php

namespace App\Servises;

use App\Models\Team;
use App\Models\TeamWeekday;

class TeamService
{
    public function store($data)
    {

        // Проверяем, указана ли сортировка (order_by), если нет — устанавливаем значение по умолчанию 10
        // Убедимся, что если 'order_by' отсутствует, оно будет установлено в 10
        if (!isset($data['order_by']) || $data['order_by'] === null) {
            $data['order_by'] = 10;
        }

         // Проверяем, есть ли ключ 'weekdays' в массиве $data
        $weekdays = isset($data['weekdays']) ? $data['weekdays'] : [];

        // Убираем поле 'weekdays' из данных перед сохранением основной записи
        unset($data['weekdays']);

        // Создаем команду
        $team = Team::create($data);

        // Проверяем, есть ли дни недели для сохранения
        if (!empty($weekdays)) {
            // Способ с логированием даты создания и изменения записи в БД
            foreach ($weekdays as $weekday) {
                teamWeekday::firstOrCreate([
                    'weekday_id' => $weekday,
                    'team_id' => $team->id,
                ]);
            }
        }
    }


    public function update($team, $data)
    {
        $weekdays = [];
        if (array_key_exists('weekdays', $data)) {  // Используем array_key_exists вместо isset
            $weekdays = $data['weekdays'];
        }
        unset($data['weekdays']);

        $team->update($data);

        // Теперь если $weekdays пуст, синхронизация всё равно произойдет, обнулив связи
        $team->weekdays()->sync($weekdays);
    }

    public function delete($team)
    {
        $team->delete();
    }

}