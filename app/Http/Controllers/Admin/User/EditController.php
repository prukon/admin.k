<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Servises\UserService;

class EditController extends Controller
{

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('role:admin,superadmin');
    }

    public function __invoke(User $user)
    {
        $allTeams = Team::All();
        $fields = UserField::all(); // Получаем пользовательские поля (например, теги)
        // Загрузка связи fields
        $user->load('fields');


        return response()->json([
            'user' => $user,
            'fields' => $fields,
            'teams' => $allTeams // Отправляем также список команд, если нужно
        ]);
    }
}

