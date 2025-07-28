<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MyLog;
use Illuminate\Support\Facades\Log;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    // Список статусов (для модалки "Настройки")
    // Выбираем только is_deleted = false, чтобы пользователь не видел удалённые
    public function index2(Request $request)
    {
        // Предположим, что мы можем получить текущего партнёра (или пользователя) и определить partner_id
        $partnerId = $request->user()->partner_id ?? null;


        $statuses = Status::where('partner_id', $partnerId)
//            ->where('is_deleted', false)
            ->orderBy('is_system', 'desc') // системные можно показывать первыми
            ->get();

        return response()->json([
            'statuses' => $statuses,
        ]);
    }

    public function index(Request $request)
    {
        // Получаем текущего партнёра из контекста приложения
        // (ранее брали из $request->user(), теперь — из app('current_partner'))
        $partnerId    = app('current_partner')->id;  // ← изменение #1

        // Проверяем, суперадмин ли текущий пользователь
        $currentUser  = auth()->user();              // ← изменение #2
        $isSuperadmin = $currentUser->role?->name === 'superadmin'; // ← изменение #2

    // Строим запрос по статусам для данного партнёра
    $statusesQuery = Status::where('partner_id', $partnerId); // ← изменение #1

    // Если это не суперадмин — показываем только видимые статусы
    if (! $isSuperadmin) {                                 // ← изменение #3
        $statusesQuery->where('is_visible', 1);            // ← изменение #3
    }

    // Системные статусы в начале
    $statuses = $statusesQuery
        ->orderBy('is_system', 'desc')
        ->get();

    return response()->json([
        'statuses' => $statuses,
    ]);
}


    // Создать новый пользовательский статус
    public function store2(Request $request)
    {
        $authorId = auth()->id(); // Авторизованный пользователь

        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);
        $partnerId = $request->user()->partner_id ?? null;

        // Объявляем переменную снаружи
        $status = null;

        DB::transaction(function () use ($authorId, $request, $partnerId, &$status) {
            $status = new Status();
            $status->partner_id = $partnerId;
            $status->name       = $request->input('name');
            $status->icon       = $request->input('icon');
            $status->color      = $request->input('color');
            $status->is_system  = false;
            $status->save();

            // Логирование
            MyLog::create([
                'type' => 9,
                'action' => 90,
                'author_id' => $authorId,
                'description' => ("Создание статуса расписания: " . $status->name . ", ID: " . $status->id),
                'created_at' => now(),
            ]);
        });

        // Теперь $status доступен здесь
        return response()->json([
            'success' => true,
            'status'  => $status
        ]);
    }

    public function store(Request $request)
    {
        // авторизованный пользователь
        $authorId = auth()->id();

        // 1) Валидация входящих данных
        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        // ИЗМЕНЕНИЕ #1: получаем partner_id не из $request->user(), а из контекста текущего партнёра
        $partnerId = app('current_partner')->id;

        // переменная для результата
        $status = null;

        DB::transaction(function () use ($authorId, $request, $partnerId, &$status) {
            // 2) Создаем новую запись статуса
            $status = new Status();

            // ИЗМЕНЕНИЕ #2: сохраняем partner_id, чтобы статус принадлежал текущему партнёру
            $status->partner_id = $partnerId;

            $status->name      = $request->input('name');
            $status->icon      = $request->input('icon');
            $status->color     = $request->input('color');
            $status->is_system = false;
            $status->save();

            // 3) Логируем создание
            MyLog::create([
                'type'        => 9,
                'action'      => 90,
                'author_id'   => $authorId,
                'partner_id'  => $partnerId,     // ИЗМЕНЕНИЕ #3: логируем partner_id
                'description' => "Создание статуса расписания: {$status->name}, ID: {$status->id}",
                'created_at'  => now(),
            ]);
        });

        // 4) Возвращаем созданный статус
        return response()->json([
            'success' => true,
            'status'  => $status,
        ]);
    }


    // Обновить пользовательский статус
    public function update2(Request $request, $id)
    {
        $authorId = auth()->id();
        $status   = Status::findOrFail($id);

        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        // Запрещаем обновлять системные статусы
        if ($status->is_system) {
            return response()->json(['error' => 'Системные статусы нельзя редактировать.'], 403);
        }

        DB::transaction(function () use ($authorId, $request, $status) {
            // Сохраняем старые значения
            $oldName  = $status->name;
            $oldIcon  = $status->icon;
            $oldColor = $status->color;

            // Обновляем поля
            $status->update([
                'name'  => $request->input('name'),
                'icon'  => $request->input('icon'),
                'color' => $request->input('color'),
            ]);

            // Формируем текст лога с переносами строк
            $description = sprintf(
                "Обновление статуса расписания: %s, ID: %d.\nБыло: %s, %s, %s\nСтало: %s, %s, %s",
                $status->name,
                $status->id,
                $oldName,
                $oldIcon,
                $oldColor,
                $status->name,
                $status->icon,
                $status->color
            );

            // Запись в лог
            MyLog::create([
                'type'        => 9,
                'action'      => 91,
                'author_id'   => $authorId,
                'description' => $description,
                'created_at'  => now(),
            ]);
        });

        return response()->json(['success' => true, 'status' => $status]);
    }

    public function update(Request $request, $id)
    {
        $authorId  = auth()->id();
        $partnerId = app('current_partner')->id; // ← ИЗМЕНЕНИЕ #1: получаем текущего партнёра

        // ИЗМЕНЕНИЕ #2: ищем статус только в пределах этого партнёра
        $status = Status::where('id', $id)
            ->where('partner_id', $partnerId)
            ->firstOrFail();

        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        // запрещаем правку системных статусов (они общие для всех партнёров)
        if ($status->is_system) {
            return response()->json([
                'error' => 'Системные статусы нельзя редактировать.'
            ], 403);
        }

        DB::transaction(function () use ($authorId, $request, $status, $partnerId) {
            // сохраняем старые значения
            $oldName  = $status->name;
            $oldIcon  = $status->icon;
            $oldColor = $status->color;

            // обновляем поля
            $status->update([
                'name'  => $request->input('name'),
                'icon'  => $request->input('icon'),
                'color' => $request->input('color'),
            ]);

            // формируем описание для лога
            $description = sprintf(
                "Обновление статуса расписания: %s, ID: %d.\n" .
                "Было: %s, %s, %s\n" .
                "Стало: %s, %s, %s",
                $status->name,
                $status->id,
                $oldName,
                $oldIcon,
                $oldColor,
                $status->name,
                $status->icon,
                $status->color
            );

            // ИЗМЕНЕНИЕ #3: сохраняем partner_id в логе
            MyLog::create([
                'type'        => 9,
                'action'      => 91,
                'author_id'   => $authorId,
                'partner_id'  => $partnerId,
                'description' => $description,
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'status'  => $status,
        ]);
    }


    public function destroy2($id)
    {
        $status = Status::findOrFail($id);
        $authorId = auth()->id(); // Авторизованный пользователь

        if ($status->is_system) {
            return response()->json(['error' => 'Системные статусы нельзя удалять.'], 403);
        }

        DB::transaction(function () use ($authorId, $status) {
            // Мягкое удаление
            $status->delete();
            // Логирование
            MyLog::create([
                'type' => 9,
                'action' => 92,
                'author_id' => $authorId,
                'description' => ("Удаление статуса расписания: " . $status->name . ", ID: " . $status->id),
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $authorId  = auth()->id();
        $partnerId = app('current_partner')->id; // ← ИЗМЕНЕНИЕ #1: получаем partner_id из контекста

        // ИЗМЕНЕНИЕ #2: ищем статус только в рамках этого партнёра
        $status = Status::where('id', $id)
            ->where('partner_id', $partnerId)
            ->firstOrFail();

        // запрет на удаление системных статусов
        if ($status->is_system) {
            return response()->json([
                'error' => 'Системные статусы нельзя удалять.'
            ], 403);
        }

        DB::transaction(function () use ($authorId, $status, $partnerId) {
            // мягкое удаление
            $status->delete();

            // логируем удаление
            MyLog::create([
                'type'        => 9,
                'action'      => 92,
                'author_id'   => $authorId,
                'partner_id'  => $partnerId, // ← ИЗМЕНЕНИЕ #3: сохраняем partner_id в логе
                'description' => "Удаление статуса расписания: {$status->name}, ID: {$status->id}",
                'created_at'  => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }



}
