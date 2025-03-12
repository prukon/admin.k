<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

use App\Models\Status;
use Illuminate\Http\Request;

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
        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $partnerId = $request->user()->partner_id ?? null;

        $status = new Status();
        $status->partner_id = $partnerId;
        $status->name       = $request->input('name');
        $status->icon       = $request->input('icon');
        $status->color      = $request->input('color');
        $status->is_system  = false;   // Создаваемые через UI — пользовательские
        $status->save();

        return response()->json(['success' => true, 'status' => $status]);
    }

    // Обновить пользовательский статус
    public function update(Request $request, $id)
    {

        Log::info("Вызывается update для ID: $id");

        $status = Status::findOrFail($id);
        Log::info("Найден статус: " . json_encode($status));

        $request->validate([
            'name'  => 'required|string|max:255',
            'icon'  => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $status = Status::findOrFail($id);

        // Запрещаем обновлять системные статусы
        if ($status->is_system) {
            return response()->json(['error' => 'Системные статусы нельзя редактировать.'], 403);
        }

        $status->name  = $request->input('name');
        $status->icon  = $request->input('icon');
        $status->color = $request->input('color');
        $status->save();

        return response()->json(['success' => true, 'status' => $status]);
    }


    public function destroy($id)
    {
                Log::info("Вызывается delete для ID: $id");

        $status = Status::findOrFail($id);

        if ($status->is_system) {
            return response()->json(['error' => 'Системные статусы нельзя удалять.'], 403);
        }

        // Мягкое удаление
        $status->delete();

        return response()->json(['success' => true]);
    }


}
