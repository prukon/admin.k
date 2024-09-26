<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreRequest;
use App\Models\Log;
use App\Models\Team;
use App\Models\teamWeekday;
use App\Servises\TeamService;
use Carbon\Carbon;

class StoreController extends Controller
{

    public $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
        $this->middleware('admin');}

    public function __invoke(StoreRequest $request)
    {

        $authorId = auth()->id(); // Авторизованный пользователь
        // Проверяем, указана ли сортировка (order_by), если нет — устанавливаем значение по умолчанию 10

        $data = $request->validated();
        $this->service->store($data);


//        $team = Team::find($data['team_id']);
//        $teamName = $team ? $team->title : '-';


        $weekdaysMap = [
            1 => 'пн',
            2 => 'вт',
            3 => 'ср',
            4 => 'чт',
            5 => 'пт',
            6 => 'сб',
            7 => 'вс',
        ];

// Проверяем, что поле 'weekdays' существует в массиве $data
        $weekdaysFormatted = [];
        if (isset($data['weekdays']) && is_array($data['weekdays'])) {
            $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
            }, $data['weekdays']);
        }

        Log::create([
            'type' => 3, // Лог для обновления групп
            'action' => 1, // Лог для создания учетной записи
            'author_id' => $authorId,
            'description' => sprintf(
                "Название: %s, дни недели: %s, сортировка: %s, активность: %s",
                $data['title'],
                $weekdaysFormatted ? implode(', ', $weekdaysFormatted) : 'не указаны', // Если массив пустой, выводим сообщение, что не указаны
                isset($data['order_by']) ? $data['order_by'] : 'не указана', // Если сортировка не указана, записываем "-"
                $data['is_enabled'] ? 'Да' : 'Нет'
            ),
            'created_at' => now(),
        ]);




        return redirect()->route('admin.team.index');
    }

}
