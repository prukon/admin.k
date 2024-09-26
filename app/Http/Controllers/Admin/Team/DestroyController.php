<?php

namespace App\Http\Controllers\Admin\Team;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Team;

class DestroyController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Team $team)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $team->delete();

        Log::create([
            'type' => 3, // Лог для обновления групп
            'action' => 3,
            'author_id' => $authorId,
            'description' => "Удалена группа: {$team->title}.  ID: {$team->id}.",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.team.index');
    }
}
