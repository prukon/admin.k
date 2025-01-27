<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreRequest;
//use App\Models\Log;
use App\Models\MyLog;
use App\Models\Team;
use App\Servises\TeamService;
use App\Servises\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class StoreController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('role:admin,superadmin');
    }

    public function __invoke(StoreRequest $request)
    {
        // Валидация входных данных
        $data = $request->validated();

        // Создание пользователя и логгирование в транзакции
        $user = null; // Создаем переменную, чтобы хранить созданного пользователя
        DB::transaction(function () use (&$user, $data) {
            // Сохраняем пользователя через сервис и получаем объект созданного пользователя
            $user = $this->service->store($data);

            // Получаем ID авторизованного пользователя
            $authorId = auth()->id(); // Авторизованный пользователь

            // Находим группу по ID, если она существует
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            // Логируем создание пользователя
            MyLog::create([
                'type' => 2, // Лог для юзеров
                'action' => 21, // Лог для создания учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
                    $data['name'],
                    isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    $teamName,
                    $data['email'],
                    $data['is_enabled'] ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);
        });

        // Если запрос AJAX, возвращаем JSON-ответ
        if ($request->ajax()) {
            // Находим группу по ID, если она существует (повторно, чтобы передать в ответе)
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            return response()->json([
                'message' => 'Пользователь создан успешно',
                'user' => [
                    'id' => $user->id,
                    'name' => $data['name'],
                    'birthday' => isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    'start_date' => isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    'team' => $teamName,
                    'email' => $data['email'],
                    'is_enabled' => $data['is_enabled'] ? 'Да' : 'Нет',
                ]
            ], 200);
        }

        // Обычный редирект при не-AJAX запросе
        return redirect()->route('admin.user.index');
    }
}