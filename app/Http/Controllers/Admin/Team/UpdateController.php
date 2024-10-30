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
        $team = Team::find($id);

        // Проверка наличия команды и её ID перед обновлением
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или не имеет ID'], 404);
        }

        // Сохраняем старые данные для логирования
        $oldData = $team->replicate();

        try {
            // Обновление данных с использованием TeamService
            $this->service->update($team, $data);

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
                    implode(', ', $oldData->weekdays->pluck('id')->toArray()) ?: 'не указаны',
                    $oldData->order_by ?? 'не указана',
                    $oldData->is_enabled ? 'Да' : 'Нет',
                    $team->title,
                    implode(', ', $data['weekdays'] ?? []) ?: 'не указаны',
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
