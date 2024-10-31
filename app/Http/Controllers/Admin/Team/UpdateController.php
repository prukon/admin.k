<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateRequest;
use App\Models\Log;
use App\Models\Team;
use App\Servises\TeamService;
use Carbon\Carbon;

class UpdateController extends Controller
{
    protected $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(UpdateRequest $request, $id)
    {
        $authorId = auth()->id();
        $data = $request->validated();

        // Попытка загрузить команду по ID
        $team = Team::with('weekdays')->find($id); // Подгружаем связанные дни недели

        // Проверка наличия команды и её ID перед обновлением
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или не имеет ID'], 404);
        }

        // Создаём копию данных с подгруженными днями недели
        $oldData = $team->replicate();
        $oldData->setRelation('weekdays', $team->weekdays); // Подгружаем связанные данные в копию

        // Маппинг чисел в сокращения дней недели
        $weekdaysMap = [
            1 => 'пн',
            2 => 'вт',
            3 => 'ср',
            4 => 'чт',
            5 => 'пт',
            6 => 'сб',
            7 => 'вс',
        ];

        try {
            // Обновление данных с использованием TeamService
            $this->service->update($team, $data);

            // Преобразование старых и новых дней недели в сокращения
            $oldWeekdaysFormatted = $oldData->weekdays->pluck('id')->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            $newWeekdaysFormatted = collect($data['weekdays'] ?? [])->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            // Логирование изменений
            Log::create([
                'type' => 3,
                'action' => 32,
                'author_id' => $authorId,
                'description' => sprintf(
                    "Старые данные:
                Название: %s, дни недели: %s, сортировка: %s, активность: %s.
                Новые данные:
                Название: %s, дни недели: %s, сортировка: %s, активность: %s.",
                    $oldData->title,
                    !empty($oldWeekdaysFormatted) ? implode(', ', $oldWeekdaysFormatted) : 'не указаны',
                    $oldData->order_by ?? 'не указана',
                    $oldData->is_enabled ? 'Да' : 'Нет',
                    $team->title,
                    !empty($newWeekdaysFormatted) ? implode(', ', $newWeekdaysFormatted) : 'не указаны',
                    $team->order_by ?? 'не указана',
                    $team->is_enabled ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Группа успешно обновлена']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

