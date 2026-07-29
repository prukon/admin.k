<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ReorderLessonOccurrenceStatusesRequest;
use App\Http\Requests\Admin\StoreLessonOccurrenceStatusRequest;
use App\Http\Requests\Admin\UpdateLessonOccurrenceStatusRequest;
use App\Http\Requests\LessonOccurrenceStatus\FilterRequest;
use App\Models\LessonOccurrenceStatus;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use App\Support\BuildsLogTable;
use Carbon\Carbon;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LessonOccurrenceStatusController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        return view('admin.lessonPackages.index', [
            'activeTab' => 'occurrence-statuses',
            'weekdays' => [],
        ]);
    }

    /**
     * Вкладка «Статусы занятий» в разделе журнала /schedule.
     * Тот же CRUD API, что и у admin/lesson-packages/occurrence-statuses.
     */
    public function scheduleIndex(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        return view('admin.schedule.index', [
            'activeTab' => 'occurrence-statuses',
        ]);
    }

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $validated = $request->validate([
            'title' => 'nullable|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'consumes' => 'nullable|string',
            'draw' => 'nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $baseQuery = LessonOccurrenceStatus::query()
            ->forPartner($partnerId);

        $titleSearch = trim((string) ($validated['title'] ?? ''));
        if ($titleSearch === '' && $request->filled('search.value')) {
            $titleSearch = trim((string) $request->input('search.value'));
        }

        if ($titleSearch !== '') {
            $like = '%'.$titleSearch.'%';
            $baseQuery->where(function ($q) use ($like, $titleSearch) {
                $q->where('title', 'like', $like)
                    ->orWhere('code', 'like', $like);

                if (ctype_digit($titleSearch)) {
                    $q->orWhere('id', (int) $titleSearch);
                }
            });
        }

        if (! empty($validated['type'])) {
            if ($validated['type'] === 'system') {
                $baseQuery->where('is_system', true);
            } elseif ($validated['type'] === 'custom') {
                $baseQuery->where('is_system', false);
            }
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('is_active', true);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('is_active', false);
            }
        }

        if (! empty($validated['consumes'])) {
            if ($validated['consumes'] === 'yes') {
                $baseQuery->where('consumes_lesson', true);
            } elseif ($validated['consumes'] === 'no') {
                $baseQuery->where('consumes_lesson', false);
            }
        }

        $totalRecords = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
            ->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef = $request->input('columns', []);
        $orderColumnName = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'title':
                $baseQuery->orderBy('title', $orderDir)->orderBy('id', 'asc');
                break;
            case 'type_label':
                $baseQuery->orderBy('is_system', $orderDir)->orderBy('sort_order', 'asc');
                break;
            case 'consumes_lesson_label':
                $baseQuery->orderBy('consumes_lesson', $orderDir)->orderBy('sort_order', 'asc');
                break;
            case 'is_active_label':
                $baseQuery->orderBy('is_active', $orderDir)->orderBy('sort_order', 'asc');
                break;
            case 'sort_order':
            default:
                $baseQuery->orderBy('sort_order', $orderDir)->orderBy('id', 'asc');
                break;
        }

        $start = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $statuses = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $statuses->map(function (LessonOccurrenceStatus $status) {
            return [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'title' => $status->title,
                'color' => $status->color,
                'icon' => $status->icon ?? '',
                'code' => $status->code,
                'is_system' => $status->is_system ? 1 : 0,
                'type_label' => $status->is_system ? 'Системный' : 'Свой',
                'consumes_lesson' => $status->consumes_lesson ? 1 : 0,
                'consumes_lesson_label' => $status->consumes_lesson ? 'Да' : 'Нет',
                'is_active' => $status->is_active ? 1 : 0,
                'is_active_label' => $status->is_active ? 'Да' : 'Нет',
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
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

            $this->auditLogger->record(
                AuditEvent::LessonOccurrenceStatusCreated,
                AuditContext::make($this->formatStatusSnapshotDescription($status))
                    ->withTarget($status, $status->title)
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );
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

        $beforeSnapshot = $this->statusAuditSnapshot($lessonOccurrenceStatus);

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

        $lessonOccurrenceStatus->refresh();

        $changes = $this->diffStatusAuditSnapshots(
            $beforeSnapshot,
            $this->statusAuditSnapshot($lessonOccurrenceStatus),
        );

        if ($changes !== []) {
            $this->auditLogger->record(
                AuditEvent::LessonOccurrenceStatusUpdated,
                AuditContext::make(implode("\n", $changes))
                    ->withTarget($lessonOccurrenceStatus, $lessonOccurrenceStatus->title)
                    ->withCreatedAt(now())
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Статус обновлён']);
        }

        return redirect()->route('admin.lesson-packages.occurrence-statuses.index');
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

        $this->auditLogger->record(
            AuditEvent::LessonOccurrenceStatusDeleted,
            AuditContext::make("Статус занятия удалён: {$lessonOccurrenceStatus->title}. ID: {$lessonOccurrenceStatus->id}.")
                ->withTarget($lessonOccurrenceStatus, $lessonOccurrenceStatus->title)
                ->withCreatedAt(now())
        );

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
            ->forPartner($partnerId)
            ->count();

        $ownedCount = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
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
                    ->forPartner($partnerId)
                    ->whereKey((int) $row['id'])
                    ->update(['sort_order' => (int) $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Порядок сохранён']);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('lesson_occurrence_status');
    }

    /**
     * @return array<string, string>
     */
    private function statusAuditSnapshot(LessonOccurrenceStatus $status): array
    {
        return [
            'title' => (string) ($status->title ?? ''),
            'color' => (string) ($status->color ?? ''),
            'icon' => $this->auditTextValue($status->icon, 'без иконки'),
            'sort_order' => (string) ($status->sort_order ?? 0),
            'consumes_lesson' => $status->consumes_lesson ? 'Да' : 'Нет',
            'is_active' => $status->is_active ? 'Да' : 'Нет',
            'type' => $status->is_system ? 'Системный' : 'Свой',
        ];
    }

    private function formatStatusSnapshotDescription(LessonOccurrenceStatus $status): string
    {
        $snapshot = $this->statusAuditSnapshot($status);

        return implode("\n", [
            "Название: {$snapshot['title']}",
            "Цвет: {$snapshot['color']}",
            "Иконка: {$snapshot['icon']}",
            "Порядок: {$snapshot['sort_order']}",
            "Списывает занятие: {$snapshot['consumes_lesson']}",
            "Активность: {$snapshot['is_active']}",
            "Тип: {$snapshot['type']}",
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffStatusAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'title' => 'Название',
            'color' => 'Цвет',
            'icon' => 'Иконка',
            'sort_order' => 'Порядок',
            'consumes_lesson' => 'Списывает занятие',
            'is_active' => 'Активность',
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    private function auditTextValue(mixed $value, string $emptyLabel): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $emptyLabel;
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
            ->forPartner($partnerId)
            ->max('sort_order');

        return (int) $max + 10;
    }
}
