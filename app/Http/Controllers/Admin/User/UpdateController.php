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

        // Получаем старые значения пользовательских полей
        $oldCustomData = UserFieldValue::where('user_id', $user->id)->get()->keyBy('field_id')->toArray();

        DB::transaction(function () use ($user, $authorId, $data, $oldData, $oldTeamName, $oldCustomData) {
            // Обновление пользователя с помощью сервиса
            $this->service->update($user, $data);

            // Обработка команды для новой команды
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            // Логирование изменений в кастомных полях
            $customLogDescription = '';
            if (array_key_exists('custom', $data) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        $oldValue = $oldCustomData[$userField->id]['value'] ?? null;
                        if ($oldValue !== $value) {
                            $customLogDescription .= sprintf(
                                "\n%s: %s -> %s",
                                $userField->name, // Используем название поля
                                $oldValue ?? '-',
                                $value !== null ? $value : '-' // Новое значение или "-"
                            );
                        }
                    }
                }
            }

            // Создание лога обновления данных пользователя с добавлением изменений в кастомных полях
            Log::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 22, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Старые:\n Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s.\nНовые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s%s",
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
                    $data['is_enabled'] ? 'Да' : 'Нет',
                    $customLogDescription // Добавляем описание изменений кастомных полей
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

//Обработка создания/изменения/удаления доп. полей
    public function storeFields(Request $request)
    {
        // Получаем все поля, которые были отправлены
        $fields = $request->input('fields', []);
        $authorId = auth()->id(); // Получаем ID текущего пользователя

        // Выполняем все операции в рамках транзакции
        DB::transaction(function () use ($fields, $request, $authorId) {
            // Сначала удалим те теги, которых больше нет в запросе
            $existingFieldIds = UserField::pluck('id')->toArray();
            $submittedFieldIds = array_filter(array_column((array) $fields, 'id'));

            // Находим все ID, которые есть в базе, но не были отправлены в запросе (т.е. удалены)
            $fieldsToDelete = array_diff($existingFieldIds, $submittedFieldIds);


            $fieldsToDeleteRecords = UserField::whereIn('id', $fieldsToDelete)->get();

            // Удаляем все найденные поля
            if (!empty($fieldsToDelete)) {
                UserField::whereIn('id', $fieldsToDelete)->delete();

                // Логируем удаление полей
                foreach ($fieldsToDeleteRecords as $deletedField) {
                    Log::create([
                        'type' => 2,
                        'action' => 25,
                        'author_id' => $authorId,
                        'description' => "Удалено поле: {$deletedField->name}. ID: {$deletedField->id}",
                        'created_at' => now(),
                    ]);
                }
            }

            // Обрабатываем каждый тег в запросе
            foreach ($fields as $key => $field) {
                $fieldId = $field['id'] ?? null;

                // Транслитерируем name в slug с помощью Str::slug
                $slug = Str::slug($field['name']); // Применяем транслитерацию и замену пробелов на дефисы

                // Валидация для каждого поля
                $request->validate([
                    "fields.$key.name" => 'required|string|max:255',
                    "fields.$key.field_type" => 'required|string',
                ]);

                if ($fieldId) {
                    // Обновление существующего поля
                    $userField = UserField::findOrFail($fieldId);

                    // Проверяем, изменились ли значения
                    $changes = [];
                    if ($userField->name !== $field['name']) {
                        $changes[] = "имя с '{$userField->name}' на '{$field['name']}'";
                    }
                    if ($userField->field_type !== $field['field_type']) {
                        $changes[] = "тип с '{$userField->field_type}' на '{$field['field_type']}'";
                    }

                    if (!empty($changes)) {
                        $userField->update([
                            'name' => $field['name'],
                            'slug' => $slug,
                            'field_type' => $field['field_type'],
                        ]);

                        // Логируем только если были изменения
                        Log::create([
                            'type' => 2,
                            'action' => 25,
                            'author_id' => $authorId,
                            'description' => "Обновлено поле ID: $fieldId. изменения: " . implode(', ', $changes),
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    // Создание нового поля
                    $newField = UserField::create([
                        'name' => $field['name'],
                        'slug' => $slug,
                        'field_type' => $field['field_type'],
                    ]);

                    // Логируем создание нового поля
                    Log::create([
                        'type' => 2,
                        'action' => 25,
                        'author_id' => $authorId,
                        'description' => "Создано новое поле {$field['name']}. ID: {$newField->id}",
                        'created_at' => now(),
                    ]);
                }
            }
        });

        // Возвращаем успешный ответ
        return response()->json(['message' => 'Поля успешно сохранены']);
    }
}
