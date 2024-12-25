<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminUpdateRequest;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Carbon\Carbon;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Models\Log;

//use Illuminate\Support\Facades\Log;


class UpdateController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
        $this->middleware('role:admin,superadmin');
    }

    public function __invoke(AdminUpdateRequest $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $user->id)->first();
        $oldTeam = Team::find($user->team_id);
        $oldTeamName = $oldTeam ? $oldTeam->title : '-';
        $data = $request->validated();
//        Log::info('Полученные данные:', $data);

        var_dump('Содержимое массива $data:', $data);

        DB::transaction(function () use ($user, $authorId, $data, $oldData, $oldTeamName) {
            // Обновление пользователя с помощью сервиса
            $this->service->update($user, $data);

            // Обработка команды для новой команды
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            // Создание лога обновления данных пользователя
            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 22, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Старые:\n Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s.\nНовые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s",
                    $oldData->name,
                    isset($oldData->birthday) ? Carbon::parse($oldData->birthday)->format('d.m.Y') : '-',
                    isset($oldData->start_date) ? Carbon::parse($oldData->start_date)->format('d.m.Y') : '-',
                    $oldTeamName,
                    $oldData->email,
                    $oldData->is_enabled ? 'Да' : 'Нет',
                    $data['name'],
                    isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    $teamName,
                    $data['email'],
                    $data['is_enabled'] ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);
        });
        return response()->json(['message' => 'Пользователь успешно обновлен']);
    }

    public function updatePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|min:8',
        ]);

        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($request, $id, $authorId) {
            // Поиск пользователя
            $user = User::findOrFail($id);

            // Обновление пароля
            $user->password = Hash::make($request->password);
            $user->save();

            // Логирование изменения пароля
            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 25, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ("Пользователю " . $user->name . " был изменен пароль."),
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function storeFields(Request $request)
    {
        // Получаем все поля, которые были отправлены
        $fields = $request->input('fields', []);

        // Сначала удалим те теги, которых больше нет в запросе
        // Получаем все существующие теги
        $existingFieldIds = UserField::pluck('id')->toArray();
        $submittedFieldIds = array_filter(array_column((array) $fields, 'id'));

        // Находим все ID, которые есть в базе, но не были отправлены в запросе (т.е. удалены)
        $fieldsToDelete = array_diff($existingFieldIds, $submittedFieldIds);

        // Удаляем все найденные поля
        UserField::whereIn('id', $fieldsToDelete)->delete();

        // Обрабатываем каждый тег в запросе
// Обрабатываем каждый тег в запросе
        foreach ($fields as $key => $field) {
            $fieldId = $field['id'] ?? null;

            // Транслитерируем name в slug с помощью Str::slug
            $slug = Str::slug($field['name']); // Применяем транслитерацию и замену пробелов на дефисы

            // Валидация для каждого поля
            $request->validate([
                "fields.$key.name" => 'required|string|max:255',
                "fields.$key.slug" => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('user_fields', 'slug')->ignore($fieldId),
                ],
                "fields.$key.field_type" => 'required|string',
            ]);

            // Если у поля есть ID, то это обновление существующего тега
            if ($fieldId) {
                $userField = UserField::findOrFail($fieldId);
                $userField->update([
                    'name' => $field['name'],
                    'slug' => $slug, // Используем транслитерированный slug
                    'field_type' => $field['field_type'],
                ]);
            } else {
                // Если ID нет, создаем новый тег
                UserField::create([
                    'name' => $field['name'],
                    'slug' => $slug, // Используем транслитерированный slug
                    'field_type' => $field['field_type'],
                ]);
            }
        }


        // Возвращаем успешный ответ
        return response()->json(['message' => 'Поля успешно сохранены']);
    }


}
