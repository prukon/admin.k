<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreRequest;
use App\Models\Log;
use App\Models\Team;
use App\Servises\TeamService;
use App\Servises\UserService;
use Carbon\Carbon;

class StoreController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(StoreRequest $request)
    {
        $data = $request->validated();
        $this->service->store($data);
        $authorId = auth()->id(); // Авторизованный пользователь

        $team = Team::find($data['team_id']);
        $teamName = $team ? $team->title : '-';

        Log::create([
            'type' => 2, // Лог для обновления юзеров
            'action' => 1, // Лог для создания учетной записи
            'author_id' => $authorId,
            'description' => sprintf(
                "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
                $data['name'],
                isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
                isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
                $teamName,
                $data['email'],
                $data['is_enabled'] ? 'Да' : 'Нет'
            ),
            'created_at' => now(),
        ]);


        return redirect()->route('admin.user.index');
    }

}