<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminUpdateRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;
use App\Servises\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $data = $request->validated();
        $this->service->update($user, $data);
//        return redirect()->route('admin.user.index');
        return redirect()->route('admin.user.edit', ['user' => $user->id]);

    }

    public function updatePassword(Request $request, $id)
    {

        \Log::info('Метод запроса: ' . $request->method()); // Логируем метод
        \Log::info('CSRF токен: ' . $request->header('X-CSRF-TOKEN')); // Логируем CSRF токен


        $request->validate([
            'password' => 'required|min:8',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['success' => true]);
    }
}
