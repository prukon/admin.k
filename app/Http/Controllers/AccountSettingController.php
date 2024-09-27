<?php


namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateRequest;
use App\Models\Log;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;


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
        $user = Auth::user();


        return view('user.edit', compact('user',
            'allTeams',

        ));
    }

    public function update(UpdateRequest $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $authorId)->first();
        $currentUser = Auth::user();
        $user = Auth::user();

        $data = $request->validated();
        $this->service->update($user, $data);
        $authorName = User::where('id', $authorId)->first()->name;

        // Логируем успешное обновление
        Log::create([
            'type' => 2, // Лог для обновления юзеров
            'action' => 23, // Лог для обновления учетной записи
            'author_id' => $authorId,
            'description' => "Имя: $authorName. ID: $authorId. \nСтарые:\n (" . Carbon::parse($oldData->birthday)->format('d.m.Y') . ", $oldData->email). \nНовые:\n (" . Carbon::parse($data['birthday'])->format('d.m.Y') . ", {$data['email']})",
            'created_at' => now(),
        ]);

        return redirect()->route('user.edit', ['user' => $user->id]);

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

//    public function getLogsData()
//    {
//        $logs = Log::with('author')->select('logs.*');
//
//        return DataTables::of($logs)
//            ->addColumn('author', function ($log) {
//                return $log->author ? $log->author->name : 'Неизвестно';
//            })
//            ->editColumn('created_at', function ($log) {
//                return $log->created_at->format('d.m.Y / H:i:s');
//            })
//            ->editColumn('type', function ($log) {
//                // Логика для преобразования типа
//                $typeLabels = [
//                    2 => 'Обновление юзера из учетной записи',
//                ];
//                return $typeLabels[$log->type] ?? 'Неизвестный тип';
//            })
//            ->make(true);
//    }

}
