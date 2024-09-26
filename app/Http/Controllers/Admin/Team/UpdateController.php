<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateRequest;
use App\Models\Log;
use App\Models\Team;
use App\Models\TeamWeekday;
use App\Servises\TeamService;
use Carbon\Carbon;

class UpdateController extends Controller
{
    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(UpdateRequest $request, Team $team)
    {
        $authorId = auth()->id(); // Авторизованный пользователь

        // Получаем старые данные перед обновлением
        $oldData = Team::where('id', $team->id)->first();

        // Логика работы с днями недели
        $weekdaysMap = [
            1 => 'пн',
            2 => 'вт',
            3 => 'ср',
            4 => 'чт',
            5 => 'пт',
            6 => 'сб',
            7 => 'вс',
        ];

        // Получаем старые дни недели
        $oldWeekdays = TeamWeekday::where('team_id', $oldData->id)->pluck('weekday_id')->toArray();
        $oldWeekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
            return isset($weekdaysMap[$day]) ? $weekdaysMap[$day] : $day;
        }, $oldWeekdays);

        // Валидация входных данных с фронта
        $data = $request->validated();

        // Обновление данных
        $this->service->update($team, $data);

        // Проверяем, что поле 'weekdays' существует в массиве $data
        $weekdaysFormatted = [];
        if (isset($data['weekdays']) && is_array($data['weekdays'])) {
            $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                return isset($weekdaysMap[$day]) ? $weekdaysMap[$day] : $day;
            }, $data['weekdays']);
        }

        // Логирование старых и новых данных
        Log::create([
            'type' => 3, // Лог для обновления групп
            'action' => 2, // Лог для обновления учетной записи
            'author_id' => $authorId,
            'description' => sprintf(
                "Старые:
                Название: %s, дни недели: %s, сортировка: %s, активность: %s.
                Новые:
                Название: %s, дни недели: %s, сортировка: %s, активность: %s.",

                // Старые данные
                $oldData->title,
                !empty($oldWeekdaysFormatted) ? implode(', ', $oldWeekdaysFormatted) : 'не указаны',
                $oldData->order_by !== null ? $oldData->order_by : '-',
                $oldData->is_enabled ? 'Да' : 'Нет',

                // Новые данные
                $data['title'],
                !empty($weekdaysFormatted) ? implode(', ', $weekdaysFormatted) : 'не указаны',
                isset($data['order_by']) ? $data['order_by'] : 'не указана',
                $data['is_enabled'] ? 'Да' : 'Нет'
            ),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.team.edit', ['team' => $team->id]);
    }
}
