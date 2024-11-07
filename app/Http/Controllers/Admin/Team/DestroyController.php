<?php
namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class DestroyController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($team, $authorId) {
            // Обновляем пользователей, устанавливая team_id в null
            \App\Models\User::where('team_id', $team->id)->update(['team_id' => null]);

            // Мягкое удаление группы
            $team->delete();

            // Логирование
            Log::create([
                'type' => 3, // Лог для обновления групп
                'action' => 33,
                'author_id' => $authorId,
                'description' => "Группа помечена как удалённая: {$team->title}. ID: {$team->id}.",
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Группа и её связь с пользователями успешно помечены как удалённые']);
    }
}

