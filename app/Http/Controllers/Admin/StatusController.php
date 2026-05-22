<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreScheduleStatusRequest;
use App\Http\Requests\Admin\UpdateScheduleStatusRequest;
use App\Models\MyLog;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\PartnerContext;

class StatusController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    // Список статусов (для модалки "Настройки")
    // Выбираем только is_deleted = false, чтобы пользователь не видел удалённые
    public function index2(Request $request)
    {
        $partnerId = $this->partnerId();


        return response()->json([
            'statuses' => $this->statusesForScheduleJson($partnerId, true),
        ]);
    }

    public function index(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        return response()->json([
            'statuses' => $this->statusesForScheduleJson($partnerId, $this->isSuperAdmin()),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusesForScheduleJson(int $partnerId, bool $isSuperadmin): array
    {
        $query = Status::query()->forSchedulePartner($partnerId);

        if (! $isSuperadmin && Schema::hasColumn('statuses', 'is_visible')) {
            $query->where(function ($q) {
                $q->where('is_visible', 1)->orWhereNull('is_visible');
            });
        }

        if (Schema::hasColumn('statuses', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (Status $status) => $status->toScheduleJsonArray())
            ->values()
            ->all();
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

    public function store(StoreScheduleStatusRequest $request)
    {
        $authorId = auth()->id();
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $sortOrder = isset($data['sort_order'])
            ? (int) $data['sort_order']
            : $this->nextCustomSortOrder($partnerId);

        $status = null;

        DB::transaction(function () use ($authorId, $data, $partnerId, $sortOrder, &$status) {
            $status = new Status();
            $status->partner_id = $partnerId;
            $status->name = $data['name'];
            $status->icon = $data['icon'] ?? null;
            $status->color = $data['color'] ?? null;
            $status->sort_order = $sortOrder;
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

    public function update(UpdateScheduleStatusRequest $request, $id)
    {
        $authorId  = auth()->id();
        $partnerId = $this->requirePartnerId();

        $status = Status::query()
            ->where('id', $id)
            ->where('partner_id', $partnerId)
            ->firstOrFail();

        if ($status->is_system) {
            return response()->json([
                'message' => 'Системные статусы нельзя редактировать.',
            ], 403);
        }

        $data = $request->validated();

        DB::transaction(function () use ($authorId, $data, $status, $partnerId) {
            $oldName = $status->name;
            $oldIcon = $status->icon;
            $oldColor = $status->color;
            $oldSort = $status->sort_order;

            $status->update([
                'name' => $data['name'],
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
                'sort_order' => (int) $data['sort_order'],
            ]);

            $description = sprintf(
                "Обновление статуса расписания: %s, ID: %d.\n" .
                "Было: %s, %s, %s, сортировка %d\n" .
                "Стало: %s, %s, %s, сортировка %d",
                $status->name,
                $status->id,
                $oldName,
                $oldIcon,
                $oldColor,
                $oldSort,
                $status->name,
                $status->icon,
                $status->color,
                $status->sort_order,
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
        $partnerId = $this->requirePartnerId(); // ← ИЗМЕНЕНИЕ #1: получаем partner_id из контекста

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

    private function nextCustomSortOrder(int $partnerId): int
    {
        $max = Status::query()
            ->where('partner_id', $partnerId)
            ->where('is_system', false)
            ->max('sort_order');

        return ((int) $max) + 10;
    }
}
