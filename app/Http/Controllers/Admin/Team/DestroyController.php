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

        DB::beginTransaction();
        try {
            // Обновляем пользователей, устанавливая team_id в null
            \App\Models\User::where('team_id', $team->id)->update(['team_id' => null]);

            // Удаляем группу
            $team->delete();

            // Логирование
            Log::create([
                'type' => 3, // Лог для обновления групп
                'action' => 33,
                'author_id' => $authorId,
                'description' => "Удалена группа: {$team->title}. ID: {$team->id}.",
                'created_at' => now(),
            ]);

            DB::commit(); // Фиксируем транзакцию

            return response()->json(['message' => 'Группа и ее связь с пользователями успешно удалены']);
        } catch (\Exception $e) {
            DB::rollBack(); // Откатываем транзакцию в случае ошибки

            return response()->json(['message' => 'Ошибка при удалении группы и обновлении пользователей'], 500);
        }
    }

}
