<?php

namespace App\Servises;

use App\Models\Team;

class TeamService
{
    public function store($data)
    {
        $data['order_by'] = $data['order_by'] ?? 10;
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $team = Team::create($data);

        if (!empty($weekdays) && $team->id) {
            $team->weekdays()->sync($weekdays);
        }
    }

    public function update($team, $data)
    {
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        // Обновляем данные команды, проверяем, что команда действительно сохранена
        if ($team->update($data) && $team->exists && $team->id) {
            $team->weekdays()->sync($weekdays);
        } else {
            throw new \Exception("Ошибка: команда не обновлена или team_id не существует.");
        }
    }

    public function delete($team)
    {
        $team->delete();
    }
}
