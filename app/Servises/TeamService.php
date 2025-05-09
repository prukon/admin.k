<?php

namespace App\Servises;

use App\Models\Team;
use Illuminate\Support\Facades\DB;


use Carbon\Carbon;
use App\Models\MyLog;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;


class TeamService
{
    public function store($data)
    {
        $partnerId = app('current_partner')->id;

        $data['order_by'] = $data['order_by'] ?? 10;
        $data['partner_id'] = $partnerId ?? 1; // Устанавливаем partner_id = 1, если не передан в данных
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $team = Team::create($data);

        if (!empty($weekdays) && $team->id) {
            $team->weekdays()->sync($weekdays);
        }

        return $team; // Возвращаем созданную команду

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

    public function storeWithLogging(array $data, int $authorId): Team
    {
        $team = DB::transaction(function () use ($data, $authorId) {
            $team = $this->store($data);

//            $weekdaysFormatted = $this->formatWeekdays($data['weekdays'] ?? []);


            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];
            $weekdaysFormatted = [];
            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
                }, $data['weekdays']);
            }

            $description = $this->generateLogDescription($data, $weekdaysFormatted);
            $this->logCreation($authorId, $description);

            return $team;
        });


        return $team;

    }

    private function generateLogDescription(array $data, array $weekdaysFormatted): string
    {
        return sprintf(
            "Название: %s, дни недели: %s, сортировка: %s, активность: %s",
            $data['title'] ?? '—',
            $weekdaysFormatted ? implode(', ', $weekdaysFormatted) : 'не указаны',
            $data['order_by'] ?? 'не указана',
            !empty($data['is_enabled']) ? 'Да' : 'Нет'
        );
    }

    private function logCreation(int $authorId, string $description): void
    {
        $partnerId = app('current_partner')->id;

        MyLog::create([
            'type' => 3,
            'action' => 31,
            'author_id' => $authorId,
            'partner_id'  => $partnerId,
            'description' => $description,
            'created_at' => Carbon::now(),
        ]);
    }


}