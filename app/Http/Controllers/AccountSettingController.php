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
use Illuminate\Support\Str;
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

//        \Log::info('Метод запроса: ' . $request->method()); // Логируем метод
//        \Log::info('CSRF токен: ' . $request->header('X-CSRF-TOKEN')); // Логируем CSRF токен

        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();


        Log::create([
            'type' => 2, // Лог для обновления юзеров
            'action' => 26, // Лог для обновления учетной записи
            'author_id' => $authorId,
            'description' => ($user->name . " изменил пароль."),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'croppedImage' => 'required|string',
        ]);

        $userName = $request->input('userName');
        $user = User::where('name', $userName)->first();

        if ($user) {
            $authorId = auth()->id(); // Авторизованный пользователь

            $imageData = $request->input('croppedImage');

            // Разбираем строку base64 и сохраняем файл
            list($type, $imageData) = explode(';', $imageData);
            list(, $imageData) = explode(',', $imageData);
            $imageData = base64_decode($imageData);

            // Генерация уникального имени файла
            $fileName = Str::random(10) . '.png';
            $path = public_path('storage/avatars/' . $fileName);

            // Сохраняем файл
            file_put_contents($path, $imageData);

            // Обновляем запись в базе данных
            $user->image_crop = $fileName;
            $user->save();

            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 28, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ($userName . " изменил аватар."),
                'created_at' => now(),
            ]);

            return response()->json(['success' => true, 'image_url' => '/storage/avatars/' . $fileName]);
        }

        return response()->json(['success' => false, 'message' => 'Пользователь не найден']);
    }

 

}
