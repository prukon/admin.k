<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreRequest;
//use App\Models\Log;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Servises\TeamService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('role:admin,superadmin');
    }

    public function __invoke(StoreRequest $request)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $data = $request->validated();

        $team = DB::transaction(function () use ($data, $authorId) {
            // Сохранение команды
            $team = $this->service->store($data);

            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            // Проверяем, что поле 'weekdays' существует в массиве $data и форматируем
            $weekdaysFormatted = [];
            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
                }, $data['weekdays']);
            }

            // Создание лога
            MyLog::create([
                'type' => 3, // Лог для обновления групп
                'action' => 31, // Лог для создания учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Название: %s, дни недели: %s, сортировка: %s, активность: %s",
                    $data['title'],
                    $weekdaysFormatted ? implode(', ', $weekdaysFormatted) : 'не указаны', // Если массив пустой, выводим сообщение, что не указаны
                    $data['order_by'] ?? 'не указана', // Если сортировка не указана, записываем "не указана"
                    $data['is_enabled'] ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);

            return $team;
        });

        // Если запрос AJAX, возвращаем JSON-ответ
        if ($request->ajax()) {
            return response()->json([
                'message' => 'Группа создана успешно',
                'team' => [
                    'id' => $team->id,
                    'title' => $team->title,
                ],
            ], 200);
        }

        return redirect()->route('admin.team.index');
    }
}
