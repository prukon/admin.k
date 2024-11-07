<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;

class EditController extends Controller
{

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('admin');
    }

    public function __invoke(User $user)
    {
        $allTeams = Team::All();

        return response()->json([
            'user' => $user,
            'teams' => $allTeams // Отправляем также список команд, если нужно

        ]);

    }
}

