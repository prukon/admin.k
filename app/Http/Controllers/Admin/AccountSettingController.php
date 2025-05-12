<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminUpdateRequest;
use App\Http\Requests\Partner\UpdateRequest;
//use App\Http\Requests\User\UpdateRequest;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
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

//use App\Models\Log;
use App\Models\MyLog;
//use Illuminate\Support\Facades\Log;


//Контроллер для админа

class AccountSettingController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function user()
    {
        $partnerId = app('current_partner')->id;
        $allTeams = Team::All();
        $user = Auth::user();
        $partners = $user->partners;
        $currentUser = Auth::user();

        // Изменено: загружаем вместе с отношением roles
        $fields = UserField::where('partner_id', $partnerId)
            ->with('roles')
            ->get();





        $userFieldValues = UserFieldValue::where('user_id', $user->id)->pluck('value', 'field_id');


        // Определяем какие поля можно редактировать
        $editableFields = $fields->mapWithKeys(function ($field) use ($currentUser) {
            // Изменено: получаем допустимые роли из pivot
            $allowedRoleIds = $field->roles->pluck('id')->toArray();
            // Изменено: проверяем по role_id вместо JSON
            $isEditable = empty($allowedRoleIds) || in_array($currentUser->role_id, $allowedRoleIds);
            return [$field->id => $isEditable];
        });

        // контроллер, метод user()
        $editableFields = $fields->mapWithKeys(function ($field) use ($currentUser) {
            $allowedRoleIds = $field->roles->pluck('id')->toArray();
            $isEditable = in_array($currentUser->role_id, $allowedRoleIds);
            return [$field->id => $isEditable];
        });







        return view('account.index',  ['activeTab' => 'user'], compact(
            'user',
            'partners',
            'allTeams',
            'fields',
            'userFieldValues',
            'editableFields',
            'currentUser' // Передаем информацию о редактируемых полях
        ));

    }

    public function update2(AdminUpdateRequest $request, User $user)
    {
        $partnerId = app('current_partner')->id;
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $authorId)->first();
        $user = Auth::user();

        $data = $request->validated();

        DB::transaction(function () use ($user, $authorId, $data, $oldData, $request, $partnerId) {

            $this->service->update($user, $data);
            $authorName = User::where('id', $authorId)->first()->name;

            $newName = $data['name'] ?? $request->input('name') ?? $oldData->name ?? 'N/A';
            $newEmail = $data['email'] ?? $request->input('email') ?? $oldData->email ?? 'N/A';


            // Логируем успешное обновление
            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 23, // Лог для обновления учетной записи
                'partner_id'  => $partnerId,
                'author_id' => $authorId,
                'description' => "Имя: $authorName. ID: $authorId. 
                Старые:\n ( $oldData->name, " . Carbon::parse($oldData->birthday)->format('d.m.Y') . ", $oldData->email). 
               Новые:\n ({$newName}, " . Carbon::parse($data['birthday'])->format('d.m.Y') . ", {$newEmail})",
                'created_at' => now(),
            ]);
        });
//        return redirect()->route('admin.cur.user.edit', ['user' => $user->id]);

        // Если запрос пришёл по AJAX, вернём JSON

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно обновлен',
                // Можно вернуть и актуальные данные пользователя, если нужно
            ]);


    }


    public function update(AdminUpdateRequest $request, User $user)
    {
        $partnerId = app('current_partner')->id;
        $authorId  = Auth::id();
        $oldData   = $user->replicate();
        $validated = $request->validated();

        // Начало обновления
        \Log::info('Начало обновления пользователя', [
            'author_id'  => $authorId,
            'user_id'    => $user->id,
            'partner_id' => $partnerId,
            'input'      => $request->all(),
            'validated'  => $validated,
        ]);

        try {
            DB::transaction(function () use ($user, $authorId, $partnerId, $validated, $oldData) {
                // Старые данные перед обновлением
                \Log::debug('Старые данные пользователя', [
                    'name'     => $oldData->name,
                    'birthday' => $oldData->birthday?->format('Y-m-d'),
                    'email'    => $oldData->email,
                ]);

                // Обновляем через сервис
                $this->service->update($user, $validated);
                $user->refresh();

                // Новые данные после обновления
                \Log::debug('Новые данные пользователя', [
                    'name'     => $user->name,
                    'birthday' => $user->birthday?->format('Y-m-d'),
                    'email'    => $user->email,
                ]);

                // Логируем в таблицу MyLog
                $authorName = Auth::user()->name;
                MyLog::create([
                    'type'        => 2,
                    'action'      => 23,
                    'partner_id'  => $partnerId,
                    'author_id'   => $authorId,
                    'description' => "Автор: {$authorName} (ID {$authorId}).\n"
                        . "Старые: {$oldData->name}, "
                        . ($oldData->birthday
                            ? Carbon::parse($oldData->birthday)->format('d.m.Y')
                            : 'null'
                        )
                        . ", {$oldData->email}.\n"
                        . "Новые: {$user->name}, "
                        . ($user->birthday
                            ? Carbon::parse($user->birthday)->format('d.m.Y')
                            : 'null'
                        )
                        . ", {$user->email}.",
                    'created_at'  => now(),
                ]);

                \Log::info('MyLog-запись успешно создана');
            });

            \Log::info('Успешная транзакция обновления пользователя', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно обновлен',
            ]);
        } catch (Exception $e) {
            \Log::error('Ошибка при обновлении пользователя', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить пользователя. Подробности в логах.',
            ], 500);
        }
    }


    public function updatePassword(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
//        $user = User::findOrFail($id);
        $user = User::findOrFail($authorId);

        DB::transaction(function () use ($user, $authorId, $request, $partnerId) {

            $user->password = Hash::make($request->password);
            $user->save();

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'partner_id'  => $partnerId,
                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }

    //обновление аватарки юзером
    public function uploadAvatar(Request $request)
    {
        $partnerId = app('current_partner')->id;
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

            DB::transaction(function () use ($path, $imageData, $user, $fileName, $authorId, $userName, $partnerId) {

                // Сохраняем файл
                file_put_contents($path, $imageData);

                // Обновляем запись в базе данных
                $user->image_crop = $fileName;
                $user->save();

                MyLog::create([
                    'type' => 2, // Лог для обновления юзеров
                    'action' => 28, // Лог для обновления учетной записи
                    'author_id' => $authorId,
                    'partner_id'  => $partnerId,
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
        $partnerId = app('current_partner')->id;
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

                DB::transaction(function () use ($path,  $user,  $authorId, $avatar, $imageName, $partnerId) {

                    // Сохранение изображения на сервере
                    if (file_put_contents($path, $avatar) === false) {
                        return response()->json(['success' => false, 'message' => 'Ошибка при сохранении изображения'], 500);
                    }

                    // Обновление записи пользователя
                    $user->update(['image_crop' => $imageName]);


                    MyLog::create([
                        'type' => 2, // Лог для обновления юзеров
                        'action' => 27, // Лог для обновления учетной записи
                        'author_id' => $authorId,
                        'partner_id'  => $partnerId,
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
            $partnerId = app('current_partner')->id;

            // Логируем удаление аватарки
            MyLog::create([
                'type' => 2,
                'action' => 29,
                'author_id' => auth()->id(),
                'partner_id'  => $partnerId,
                'description' => $user->name . " удалил аватар.",
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }


}
