<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ReorderLessonOccurrenceStatusesRequest;
use App\Http\Requests\Admin\StoreLessonOccurrenceStatusRequest;
use App\Http\Requests\Admin\UpdateLessonOccurrenceStatusRequest;
use App\Models\LessonOccurrenceStatus;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LessonOccurrenceStatusController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $statuses = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.lessonPackages.index', [
            'activeTab' => 'occurrence-statuses',
            'occurrenceStatuses' => $statuses,
            'weekdays' => [], // вкладка не использует дни недели
        ]);
    }

    public function store(StoreLessonOccurrenceStatusRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $data = $request->validated();

        $code = $this->generateUniqueCustomCode($partnerId);

        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : $this->nextSortOrder($partnerId);

        $icon = isset($data['icon']) && $data['icon'] !== '' ? (string) $data['icon'] : null;

        try {
            $status = LessonOccurrenceStatus::query()->create([
                'partner_id' => $partnerId,
                'code' => $code,
                'title' => $data['title'],
                'color' => $data['color'],
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'consumes_lesson' => (bool) ($data['consumes_lesson'] ?? false),
                'is_system' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => ['title' => [$e->getMessage()]],
            ], 422);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Статус создан',
                'status' => $status,
            ]);
        }

        return redirect()->route('admin.lesson-packages.occurrence-statuses.index');
    }

    public function update(UpdateLessonOccurrenceStatusRequest $request, LessonOccurrenceStatus $lessonOccurrenceStatus)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $lessonOccurrenceStatus->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();

        $icon = isset($data['icon']) && $data['icon'] !== '' ? (string) $data['icon'] : null;

        if ($lessonOccurrenceStatus->is_system) {
            $lessonOccurrenceStatus->update([
                'color' => $data['color'],
                'icon' => $icon,
                'sort_order' => (int) $data['sort_order'],
                'consumes_lesson' => (bool) $data['consumes_lesson'],
                'is_active' => (bool) ($data['is_active'] ?? $lessonOccurrenceStatus->is_active),
            ]);
        } else {
            $lessonOccurrenceStatus->update([
                'title' => $data['title'],
                'color' => $data['color'],
                'icon' => $icon,
                'sort_order' => (int) $data['sort_order'],
                'consumes_lesson' => (bool) $data['consumes_lesson'],
                'is_active' => (bool) ($data['is_active'] ?? $lessonOccurrenceStatus->is_active),
            ]);
        }

        return response()->json(['message' => 'Статус обновлён']);
    }

    public function destroy(Request $request, LessonOccurrenceStatus $lessonOccurrenceStatus)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $lessonOccurrenceStatus->partner_id !== $partnerId) {
            abort(404);
        }

        if ($lessonOccurrenceStatus->is_system) {
            return response()->json([
                'message' => 'Системный статус удалять нельзя',
                'errors' => ['id' => ['Системный статус удалять нельзя']],
            ], 422);
        }

        $lessonOccurrenceStatus->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Статус удалён']);
        }

        return redirect()->route('admin.lesson-packages.occurrence-statuses.index');
    }

    public function reorder(ReorderLessonOccurrenceStatusesRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $items = $request->validated()['items'];

        $ids = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $totalExpected = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->count();

        $ownedCount = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->whereIn('id', $ids)
            ->count();

        if ($ownedCount !== count(array_unique($ids)) || count($items) !== $totalExpected) {
            return response()->json([
                'message' => 'Некорректный список статусов',
                'errors' => ['items' => ['Передайте полный список статусов текущего партнёра']],
            ], 422);
        }

        DB::transaction(function () use ($partnerId, $items) {
            foreach ($items as $row) {
                LessonOccurrenceStatus::query()
                    ->where('partner_id', $partnerId)
                    ->whereKey((int) $row['id'])
                    ->update(['sort_order' => (int) $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Порядок сохранён']);
    }

    private function generateUniqueCustomCode(int $partnerId): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = 'custom_'.bin2hex(random_bytes(8));
            $exists = LessonOccurrenceStatus::query()
                ->where('partner_id', $partnerId)
                ->where('code', $code)
                ->exists();
            if (! $exists) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать уникальный код статуса');
    }

    private function nextSortOrder(int $partnerId): int
    {
        $max = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->max('sort_order');

        return (int) $max + 10;
    }
}
