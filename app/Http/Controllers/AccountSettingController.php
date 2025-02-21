<?php


namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateRequest;
//use App\Models\Log;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Servises\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $user = Auth::user();
        $fields = UserField::all();
        $userFieldValues = UserFieldValue::where('user_id', $user->id)->pluck('value', 'field_id');

        //Определяем какие поля можно редактировать
        $editableFields = $fields->mapWithKeys(function ($field) use ($user) {
            $permissions = $field->permissions ?? []; // Убираем json_decode
            $isEditable = empty($permissions) || in_array($user->role, $permissions);
            return [$field->id => $isEditable];
        });

        return view('user.edit', compact('user',
            'allTeams',
            'fields',
            'userFieldValues',
            'editableFields' // Передаем информацию о редактируемых полях

        ));
    }

    public function updateOld(UpdateRequest $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $authorId)->first();
        $user = Auth::user();

        $data = $request->validated();

        DB::transaction(function () use ($user, $authorId, $data, $oldData) {
            $this->service->update($user, $data);
            $authorName = User::where('id', $authorId)->first()->name;

            // Логируем успешное обновление
            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 23, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => "Имя: $authorName. ID: $authorId. \nСтарые:\n (" . Carbon::parse($oldData->birthday)->format('d.m.Y') . ", $oldData->email). \nНовые:\n (" . Carbon::parse($data['birthday'])->format('d.m.Y') . ", {$data['email']})",
                'created_at' => now(),
            ]);
        });
        return redirect()->route('user.edit', ['user' => $user->id]);
    }


    public function update(UpdateRequest $request, User $user)
    {
        $authorId = auth()->id();
        $oldData = User::where('id', $authorId)->first();
        $user = Auth::user();

        $data = $request->validated();

        DB::transaction(function () use ($user, $authorId, $data, $oldData) {

            // 1) Считываем старые значения (до обновления)
            $rowsOld = DB::select("SELECT field_id, value FROM user_field_values WHERE user_id = ?", [$user->id]);
            $oldCustomValues = [];
            foreach ($rowsOld as $row) {
                $oldCustomValues[$row->field_id] = $row->value;
            }

            // 2) Обновляем
            $this->service->update($user, $data);

            // 3) Считываем новые значения (после обновления)
            $rowsNew = DB::select("SELECT field_id, value FROM user_field_values WHERE user_id = ?", [$user->id]);
            $newCustomValues = [];
            foreach ($rowsNew as $row) {
                $newCustomValues[$row->field_id] = $row->value;
            }

            // 4) Формируем массив изменений
            $customChanges = [];
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $newValue) {
                    $field = UserField::where('slug', $slug)->first();
                    if (!$field) {
                        continue;
                    }

                    $fieldId = $field->id;
                    // Если у нас нет старого значения - ставим '(не задано)'
                    $oldValue = $oldCustomValues[$fieldId] ?? '(не задано)';

                    // Если старое и новое совпадают, пропускаем
                    if ($oldValue == $newValue) {
                        continue;
                    }

                    // Теперь проверяем, не являются ли оба значения "пустыми" для логики
                    // Считаем "пустым" любые вариации: '', '(не задано)' (с пробелами, разным регистром)
                    // Сразу нормализуем обе строки
                    $normOld = mb_strtolower(trim($oldValue));
                    $normNew = mb_strtolower(trim($newValue));
                    // Убираем скобки
                    $normOld = str_replace(['(', ')'], '', $normOld);
                    $normNew = str_replace(['(', ')'], '', $normNew);

                    // Если оба пусты, пропускаем
                    // (т.е. '' == '' или '' == 'не задано' и т.д.)
                    $bothEmpty = (
                        ($normOld === '' || $normOld === 'не задано') &&
                        ($normNew === '' || $normNew === 'не задано')
                    );

                    if ($bothEmpty) {
                        // Не логируем такие изменения
                        continue;
                    }

                    // Иначе логируем
                    $customChanges[] = "{$field->name}: '{$oldValue}' → '{$newValue}'";
                }
            }

            // 5) Формируем описание лога
            $authorName = User::where('id', $authorId)->first()->name;

            $oldBirthday = $oldData->birthday
                ? \Carbon\Carbon::parse($oldData->birthday)->format('d.m.Y')
                : 'Не указано';

            $newBirthday = !empty($data['birthday'])
                ? \Carbon\Carbon::parse($data['birthday'])->format('d.m.Y')
                : 'Не изменилось';

            $description = "Имя: $authorName. ID: $authorId.\n"
                . "Старые данные: ($oldBirthday, {$oldData->email})\n"
                . "Новые данные: ($newBirthday, {$data['email']})";

            // Дополняем описанием изменений доп. полей
            if (!empty($customChanges)) {
                $description .= "\nИзмененные доп. поля:\n"
                    . implode("\n", $customChanges);
            }

            // 6) Пишем лог
            MyLog::create([
                'type' => 2,
                'action' => 23,
                'author_id' => $authorId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('user.edit', ['user' => $user->id]);
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

            MyLog::create([
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

                MyLog::create([
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


                    MyLog::create([
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
            MyLog::create([
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
