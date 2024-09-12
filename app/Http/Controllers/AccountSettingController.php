<?php


namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateRequest;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// Модель Event для получения данных из базы

class AccountSettingController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $allTeams = Team::All();
        $currentUser = Auth::user();


        return view('user.edit', compact('currentUser',
            'allTeams',

        ));
    }

    public function update(UpdateRequest $request, User $user)
    {
        $currentUser = Auth::user();
        $data = $request->validated();
        $this->service->update($user, $data);


        return redirect()->route('user.edit', ['user' => $currentUser->id]);

    }

    public function updatePassword(Request $request, $id)
    {

        \Log::info('Метод запроса: ' . $request->method()); // Логируем метод
        \Log::info('CSRF токен: ' . $request->header('X-CSRF-TOKEN')); // Логируем CSRF токен


        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['success' => true]);
    }


}
