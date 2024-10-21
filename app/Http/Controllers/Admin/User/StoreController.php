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

//    public function __invoke(StoreRequest $request)
//    {
//        $data = $request->validated();
//        $this->service->store($data);
//        $authorId = auth()->id(); // Авторизованный пользователь
//
//        $team = Team::find($data['team_id']);
//        $teamName = $team ? $team->title : '-';
//
//        Log::create([
//            'type' => 2, // Лог для юзеров
//            'action' => 21, // Лог для создания учетной записи
//            'author_id' => $authorId,
//            'description' => sprintf(
//                "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
//                $data['name'],
//                isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
//                isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
//                $teamName,
//                $data['email'],
//                $data['is_enabled'] ? 'Да' : 'Нет'
//            ),
//            'created_at' => now(),
//        ]);
//
//
//        return redirect()->route('admin.user.index');
//    }
//    public function __invoke(StoreRequest $request)
//    {
//        // Валидация входных данных
//        $data = $request->validated();
//
//        // Сохраняем пользователя через сервис
//        $this->service->store($data);
//
//        // Получаем ID авторизованного пользователя
//        $authorId = auth()->id(); // Авторизованный пользователь
//
//        // Находим группу по ID, если она существует
//        $team = Team::find($data['team_id']);
//        $teamName = $team ? $team->title : '-';
//
//        // Логируем создание пользователя
//        Log::create([
//            'type' => 2, // Лог для юзеров
//            'action' => 21, // Лог для создания учетной записи
//            'author_id' => $authorId,
//            'description' => sprintf(
//                "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
//                $data['name'],
//                isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
//                isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-', // Если дата есть, форматируем, иначе "-"
//                $teamName,
//                $data['email'],
//                $data['is_enabled'] ? 'Да' : 'Нет'
//            ),
//            'created_at' => now(),
//        ]);
//
//        // Добавляем проверку на AJAX-запрос
//        if ($request->ajax()) {
//            // Возвращаем JSON-ответ с данными нового пользователя
//            return response()->json([
//                'message' => 'Пользователь создан успешно',
//                'user' => [
//                    'id' => $user->id,  // Здесь добавляем ID пользователя
//                    'name' => $data['name'],
//                    'birthday' => isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
//                    'start_date' => isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
//                    'team' => $teamName,
//                    'email' => $data['email'],
//                    'is_enabled' => $data['is_enabled'] ? 'Да' : 'Нет',
//                ]
//            ], 200); // HTTP статус 200 (ОК)
//        }
//
//        // Обычный редирект при не-AJAX запросе
//        return redirect()->route('admin.user.index');
//    }

    public function __invoke(StoreRequest $request)
    {
        // Валидация входных данных
        $data = $request->validated();

        // Сохраняем пользователя через сервис и получаем объект созданного пользователя
        $user = $this->service->store($data);

//        // Логируем пользователя для проверки
//        if (!$user) {
//            logger('Пользователь не создан. Проверьте метод store.');
//            return response()->json(['message' => 'Ошибка создания пользователя'], 500);
//        }

        // Получаем ID авторизованного пользователя
        $authorId = auth()->id(); // Авторизованный пользователь

        // Находим группу по ID, если она существует
        $team = Team::find($data['team_id']);
        $teamName = $team ? $team->title : '-';

        // Логируем создание пользователя
        Log::create([
            'type' => 2, // Лог для юзеров
            'action' => 21, // Лог для создания учетной записи
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

        // Добавляем проверку на AJAX-запрос
        if ($request->ajax()) {
            // Возвращаем JSON-ответ с данными нового пользователя
            return response()->json([
                'message' => 'Пользователь создан успешно',
                'user' => [
                    'id' => $user->id,  // Теперь $user определён
                    'name' => $data['name'],
                    'birthday' => isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    'start_date' => isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    'team' => $teamName,
                    'email' => $data['email'],
                    'is_enabled' => $data['is_enabled'] ? 'Да' : 'Нет',
                ]
            ], 200); // HTTP статус 200 (ОК)
        }

        // Обычный редирект при не-AJAX запросе
        return redirect()->route('admin.user.index');
    }




}