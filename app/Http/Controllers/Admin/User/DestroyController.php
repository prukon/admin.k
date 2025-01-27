<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
//use App\Models\Log;
use App\Models\MyLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DestroyController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,superadmin');
    }

    public function __invoke(User $user)
    {


        // Проверяем, если пользователь не существует
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        $authorId = auth()->id(); // Авторизованный пользователь


        DB::transaction(function () use ($user, $authorId) {
            // Удаление пользователя
            $user->delete();

            // Логирование удаления
            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 24,
                'author_id' => $authorId,
                'description' => "Удален пользователь: {$user->name}  ID: {$user->id}.",
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => 'Пользователь успешно удалён']);

//        return redirect()->route('admin.user.index');

    }
}
