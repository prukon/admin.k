<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreSportTypeRequest;
use App\Http\Requests\Admin\UpdateSportTypeRequest;
use App\Http\Requests\SportType\FilterRequest;
use App\Models\SportType;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use App\Support\BuildsLogTable;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SportTypeController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $this->requirePartnerId();

        return view('admin.sport-types.index');
    }

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'name'   => 'nullable|string',
            'status' => 'nullable|string',
            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $baseQuery = SportType::query()
            ->where('partner_id', $partnerId)
            ->withCount('teams');

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';
            $baseQuery->where(function ($q) use ($like, $nameSearch) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);

                if (ctype_digit($nameSearch)) {
                    $q->orWhere('id', (int) $nameSearch);
                }
            });
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('is_enabled', 0);
            }
        }

        $totalRecords = SportType::where('partner_id', $partnerId)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'sort':
                $baseQuery->orderBy('sort', $orderDir)->orderBy('name', 'asc');
                break;
            case 'teams_count':
                $baseQuery->orderBy('teams_count', $orderDir)->orderBy('name', 'asc');
                break;
            case 'is_enabled_label':
                $baseQuery->orderBy('is_enabled', $orderDir)->orderBy('name', 'asc');
                break;
            case 'name':
            default:
                $baseQuery->orderBy('name', $orderDir)->orderBy('id', 'asc');
                break;
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $sportTypes = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $sportTypes->map(function (SportType $sportType) {
            return [
                'id'               => $sportType->id,
                'sort'             => $sportType->sort,
                'name'             => $sportType->name,
                'description'      => $sportType->description ?? '',
                'teams_count'      => (int) $sportType->teams_count,
                'is_enabled'       => (int) $sportType->is_enabled,
                'is_enabled_label' => $sportType->is_enabled ? 'Да' : 'Нет',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StoreSportTypeRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validated();
        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        try {
            $sportType = SportType::create($data);

            $this->auditLogger->record(
                AuditEvent::SportTypeCreated,
                AuditContext::make($this->formatSportTypeSnapshotDescription($sportType))
                    ->withTarget($sportType, $sportType->name)
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Вид спорта с таким названием уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Вид спорта создан',
                'sport_type' => $sportType,
            ]);
        }

        return redirect()->route('admin.sport-types.index');
    }

    public function show(SportType $sportType)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $sportType->partner_id !== $partnerId) {
            abort(404);
        }

        return response()->json([
            'id' => $sportType->id,
            'name' => $sportType->name,
            'description' => $sportType->description,
            'sort' => $sportType->sort,
            'is_enabled' => (int) $sportType->is_enabled,
        ]);
    }

    public function update(UpdateSportTypeRequest $request, SportType $sportType)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $sportType->partner_id !== $partnerId) {
            abort(404);
        }

        $beforeSnapshot = $this->sportTypeAuditSnapshot($sportType);

        $data = $request->validated();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        try {
            $sportType->update($data);
            $sportType->refresh();

            $changes = $this->diffSportTypeAuditSnapshots(
                $beforeSnapshot,
                $this->sportTypeAuditSnapshot($sportType),
            );

            if ($changes !== []) {
                $this->auditLogger->record(
                    AuditEvent::SportTypeUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withTarget($sportType, $sportType->name)
                        ->withCreatedAt(now())
                );
            }
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Вид спорта с таким названием уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Вид спорта обновлён']);
    }

    public function destroy(Request $request, SportType $sportType)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $sportType->partner_id !== $partnerId) {
            abort(404);
        }

        $this->auditLogger->record(
            AuditEvent::SportTypeDeleted,
            AuditContext::make("Вид спорта удалён: {$sportType->name}. ID: {$sportType->id}.")
                ->withTarget($sportType, $sportType->name)
                ->withCreatedAt(now())
        );

        $sportType->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Вид спорта удалён',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.sport-types.index');
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('sport_type');
    }

    /**
     * @return array<string, string>
     */
    private function sportTypeAuditSnapshot(SportType $sportType): array
    {
        return [
            'name' => (string) ($sportType->name ?? ''),
            'description' => $this->auditTextValue($sportType->description, 'не указано'),
            'sort' => (string) ($sportType->sort ?? 0),
            'is_enabled' => $sportType->is_enabled ? 'Да' : 'Нет',
        ];
    }

    private function formatSportTypeSnapshotDescription(SportType $sportType): string
    {
        $snapshot = $this->sportTypeAuditSnapshot($sportType);

        return implode("\n", [
            "Название: {$snapshot['name']}",
            "Описание: {$snapshot['description']}",
            "Сортировка: {$snapshot['sort']}",
            "Активность: {$snapshot['is_enabled']}",
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffSportTypeAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'name' => 'Название',
            'description' => 'Описание',
            'sort' => 'Сортировка',
            'is_enabled' => 'Активность',
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
}
