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
    public function index(Request $request)
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

    // Создать новый пользовательский статус
    public function store(Request $request)
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

    // Обновить пользовательский статус
    public function update(Request $request, $id)
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

    public function destroy($id)
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


}
