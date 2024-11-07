<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminUpdateRequest;
use App\Models\Log;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(AdminUpdateRequest $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $user->id)->first();
        $oldTeam = Team::find($user->team_id);
        $oldTeamName = $oldTeam ? $oldTeam->title : '-';

        $data = $request->validated();

        DB::transaction(function () use ($user, $authorId, $data, $oldData, $oldTeamName) {
            // Обновление пользователя с помощью сервиса
            $this->service->update($user, $data);

            // Обработка команды для новой команды
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            // Создание лога обновления данных пользователя
            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 22, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Старые:\n Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s.\nНовые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
                    $oldData->name,
                    isset($oldData->birthday) ? Carbon::parse($oldData->birthday)->format('d.m.Y') : '-',
                    isset($oldData->start_date) ? Carbon::parse($oldData->start_date)->format('d.m.Y') : '-',
                    $oldTeamName,
                    $oldData->email,
                    $oldData->is_enabled ? 'Да' : 'Нет',
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

        return response()->json(['message' => 'Пользователь успешно обновлен']);
    }

    public function updatePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|min:8',
        ]);

        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($request, $id, $authorId) {
            // Поиск пользователя
            $user = User::findOrFail($id);

            // Обновление пароля
            $user->password = Hash::make($request->password);
            $user->save();

            // Логирование изменения пароля
            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 25, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ("Пользователю " . $user->name . " был изменен пароль."),
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }


}
