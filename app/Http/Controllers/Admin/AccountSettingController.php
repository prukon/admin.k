<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminUpdateRequest;
use App\Http\Requests\Partner\UpdateRequest;
//use App\Http\Requests\User\UpdateRequest;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Carbon\Carbon;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

use App\Models\Log;
//use Illuminate\Support\Facades\Log;





// Модель Event для получения данных из базы

class AccountSettingController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('role:admin,superadmin');

    }

    public function user()
    {
        $allTeams = Team::All();
        $user = Auth::user();
        $partners = $user->partners;



        return view('admin.editCurUser',  ['activeTab' => 'user'], compact(
            'user',
            'partners',
            'allTeams'
        ));
    }

    public function partner()
    {
        $allTeams = Team::All();
        $user = Auth::user();

        $partners = $user->partners;



        return view('admin.editCurUser',  ['activeTab' => 'partner'], compact(
            'user',
            'partners',
            'allTeams'
        ));
    }

    public function update(AdminUpdateRequest $request, User $user)
    {

        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $authorId)->first();
        $user = Auth::user();

        $data = $request->validated();

        DB::transaction(function () use ($user, $authorId, $data, $oldData) {
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
        });
        return redirect()->route('admin.cur.user.edit', ['user' => $user->id]);

    }

    public function updatePartner(UpdateRequest $request, Partner $partner)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $authorName = User::where('id', $authorId)->value('name');

        // Сохраняем старые данные до обновления
        $oldData = $partner->toArray();



        // Данные, валидированные в Request
        $data = $request->validated();

        DB::transaction(function () use ($partner, $authorId, $authorName, $oldData, $data ) {

            // Определим, есть ли вообще изменения
            $changedFields = [];
            foreach ($data as $key => $newValue) {
                // Проверяем, было ли поле в старых данных и отличается ли оно
                $oldValue = $oldData[$key] ?? null;
                if ($oldValue != $newValue) {
                    $changedFields[] = $key;
                }
            }

        $oldTitle = $oldData['title'] ?? null;
        $oldId = $oldData['id'] ?? null;

            // Если изменений нет, просто выходим из транзакции без записи лога
            if (empty($changedFields)) {
                return;
            }

            // Обновление партнёра
            $partner->update($data);

            // Словарь переводов для поля business_type
            $businessTypeTranslate = [
                'company'                     => 'ООО',
                'individual_entrepreneur'     => 'ИП',
                'physical_person'             => 'Физ. лицо',
                'non_commercial_organization' => 'НКО',
            ];


            // Формируем строку старых значений (только изменяемых полей,
            // но по требованию можно выводить абсолютно все поля $data)
            $oldString = '(' . implode(', ', array_map(function ($key) use ($oldData) {
                    return $oldData[$key] ?? '';
                }, array_keys($data))) . ')';

            // Формируем строку новых значений
//            $newString = '(' . implode(', ', array_map(function ($key) use ($data) {
//                    return $data[$key] ?? '';
//                }, array_keys($data))) . ')';


            // Формируем строку новых значений
            $newString = '(' . implode(', ', array_map(function ($key) use ($data, $businessTypeTranslate) {
                    $value = $data[$key] ?? '';
                    if ($key === 'business_type' && isset($businessTypeTranslate[$value])) {
                        $value = $businessTypeTranslate[$value];
                    }
                    return $value;
                }, array_keys($data))) . ')';

            // Собираем строку для описания лога
            // Пример вывода:
            // Имя: Админ. ID: 1.
            // Старые:
            // (11.06.1989, admin@admin.ru).
            // Новые:
            // (11.06.1989, admin@admin.ru1).
            $description = "Название: {$oldTitle}. ID: {$oldId}.\n"
                . "Старые:\n{$oldString}.\n"
                . "Новые:\n{$newString}.";

            // Записываем лог
            Log::create([
                'type'       => 2,   // или ваш тип для обновления
                'action'     => 80,  // или ваш action для обновления партнера
                'author_id'  => $authorId,
                'description'=> $description,
                'created_at' => now(),
            ]);
        });
        // Редирект с сообщением об успешном обновлении
        return redirect()->route('admin.cur.company.edit', $partner->id)
            ->with('success', 'Данные партнёра успешно обновлены.');

    }



    public function updatePassword(Request $request, $id)
    {

        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user, $authorId, $request) {

            $user->password = Hash::make($request->password);
            $user->save();

            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }

    //обновление аватарки юзером
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

            DB::transaction(function () use ($path, $imageData, $user, $fileName, $authorId, $userName) {

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
            });

            return response()->json(['success' => true, 'image_url' => '/storage/avatars/' . $fileName]);
        }

        return response()->json(['success' => false, 'message' => 'Пользователь не найден']);
    }

    //обновление аватарки админином
    public function updateAvatar(Request $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь

        // Проверка наличия аватарки в запросе
        if ($request->has('avatar')) {
            $avatar = $request->input('avatar'); // Получаем данные base64 из запроса

            // Разбираем строку base64 и проверяем её валидность
            if (preg_match('/^data:image\\/(\\w+);base64,/', $avatar, $type)) {
                $avatar = substr($avatar, strpos($avatar, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, gif и т.д.

                // Проверяем допустимые типы изображений
                if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                    return response()->json(['success' => false, 'message' => 'Недопустимый формат изображения'], 400);
                }

                $avatar = base64_decode($avatar);
                if ($avatar === false) {
                    return response()->json(['success' => false, 'message' => 'Ошибка декодирования изображения'], 400);
                }

                // Генерация уникального имени файла
                $imageName = Str::random(10) . '.' . $type;
                $path = public_path('/storage/avatars/' . $imageName);

                DB::transaction(function () use ($path,  $user,  $authorId, $avatar, $imageName) {

                    // Сохранение изображения на сервере
                    if (file_put_contents($path, $avatar) === false) {
                        return response()->json(['success' => false, 'message' => 'Ошибка при сохранении изображения'], 500);
                    }

                    // Обновление записи пользователя
                    $user->update(['image_crop' => $imageName]);


                    Log::create([
                        'type' => 2, // Лог для обновления юзеров
                        'action' => 27, // Лог для обновления учетной записи
                        'author_id' => $authorId,
                        'description' => ("Пользователю " . $user->name . " изменен аватар."),
                        'created_at' => now(),
                    ]);
                });

                return response()->json([
                    'success' => true,
                    'avatar_url' => asset('/storage/avatars/' . $imageName)
                ]);
            } else {
                return response()->json(['success' => false, 'message' => 'Некорректные данные изображения'], 400);
            }
        }

        return response()->json(['success' => false, 'message' => 'Аватарка не найдена в запросе'], 400);
    }

    public function deleteAvatar(User $user)
    {
        // Проверяем, существует ли файл аватарки
        if ($user->image_crop && file_exists(public_path('storage/avatars/' . $user->image_crop))) {
            // Удаляем файл аватарки
            unlink(public_path('storage/avatars/' . $user->image_crop));
        }
        DB::transaction(function () use ($user) {

            // Обновляем запись пользователя, устанавливая аватарку по умолчанию
            $user->update(['image_crop' => null]);

            // Логируем удаление аватарки
            Log::create([
                'type' => 2,
                'action' => 29,
                'author_id' => auth()->id(),
                'description' => $user->name . " удалил аватар.",
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }
}
