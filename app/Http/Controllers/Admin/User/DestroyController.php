<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\User;

class DestroyController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(User $user) {
        $authorId = auth()->id(); // Авторизованный пользователь
        $user->delete();

        Log::create([
            'type' => 2, // Лог для обновления юзеров
            'action' => 3,
            'author_id' => $authorId,
            'description' => "Удален пользователь: {$user->name}  ID: {$user->id}.",
            'created_at' => now(),
        ]);
        return redirect()->route('admin.user.index');
    }
}