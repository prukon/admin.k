<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\UpdateDistrictRequest;
use App\Http\Requests\District\FilterRequest;
use App\Models\District;
use App\Models\Location;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\LocationDistrictSyncService;
use App\Services\PartnerContext;
use App\Support\BuildsLogTable;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DistrictController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly LocationDistrictSyncService $locationDistrictSync,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $locationOptions = auth()->user()?->can('locations.view')
            ? Location::query()
                ->where('partner_id', $partnerId)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('admin.districts.index', compact('locationOptions'));
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

        $baseQuery = District::query()
            ->where('partner_id', $partnerId)
            ->withCount('locations');

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';
            $baseQuery->where(function ($q) use ($like, $nameSearch) {
                $q->where('name', 'like', $like);

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

        $totalRecords = District::where('partner_id', $partnerId)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'sort_order':
                $baseQuery->orderBy('sort_order', $orderDir)->orderBy('name', 'asc');
                break;
            case 'locations_count':
                $baseQuery->orderBy('locations_count', $orderDir)->orderBy('name', 'asc');
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

        /** @var \App\Models\User|null $filterActor */
        $filterActor = auth()->user();
        $canViewLocations = $filterActor?->can('locations.view') ?? false;

        if ($canViewLocations) {
            $baseQuery->with(['locations' => fn ($q) => $q->orderBy('name')]);
        }

        $districts = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $districts->map(function (District $district) use ($canViewLocations) {
            $locationsLabels = [
                'locations_label' => '',
                'locations_label_full' => '',
                'locations_names' => [],
            ];
            if ($canViewLocations && $district->relationLoaded('locations')) {
                $locationsLabels = $this->formatLocationsLabels(
                    $district->locations->sortBy('name')->pluck('name')->values()->all()
                );
            }

            return [
                'id'                   => $district->id,
                'sort_order'           => $district->sort_order,
                'name'                 => $district->name,
                'locations_count'      => (int) $district->locations_count,
                'locations_label'      => $locationsLabels['locations_label'],
                'locations_label_full' => $locationsLabels['locations_label_full'],
                'locations_names'      => $locationsLabels['locations_names'],
                'is_enabled'           => (int) $district->is_enabled,
                'is_enabled_label'     => $district->is_enabled ? 'Да' : 'Нет',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StoreDistrictRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validated();
        $syncLocations = $request->user()?->can('locations.view') ?? false;
        $locationIds = null;
        if ($syncLocations && array_key_exists('location_ids', $data)) {
            $locationIds = $data['location_ids'] ?? [];
        }
        unset($data['location_ids']);

        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        try {
            $district = District::create($data);

            if ($syncLocations && $locationIds !== null) {
                $this->locationDistrictSync->syncLocationsForDistrict($district, $locationIds);
            }

            $district->load(['locations' => fn ($q) => $q->orderBy('name')]);

            $this->auditLogger->record(
                AuditEvent::DistrictCreated,
                AuditContext::make($this->formatDistrictSnapshotDescription($district))
                    ->withTarget($district, $district->name)
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
                        'name' => ['Район с таким названием уже существует'],
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
                'message' => 'Район создан',
                'district' => $district,
            ]);
        }

        return redirect()->route('admin.districts.index');
    }

    public function show(District $district)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $district->partner_id !== $partnerId) {
            abort(404);
        }

        $payload = [
            'id' => $district->id,
            'name' => $district->name,
            'sort_order' => $district->sort_order,
            'is_enabled' => (int) $district->is_enabled,
        ];

        if (auth()->user()?->can('locations.view')) {
            $payload['location_ids'] = $district->locations()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return response()->json($payload);
    }

    public function update(UpdateDistrictRequest $request, District $district)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $district->partner_id !== $partnerId) {
            abort(404);
        }

        $district->load(['locations' => fn ($q) => $q->orderBy('name')]);
        $beforeSnapshot = $this->districtAuditSnapshot($district);

        $data = $request->validated();
        $syncLocations = $request->user()?->can('locations.view') ?? false;
        $locationIds = null;
        if ($syncLocations && array_key_exists('location_ids', $data)) {
            $locationIds = $data['location_ids'] ?? [];
        }
        unset($data['location_ids']);

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        try {
            $district->update($data);

            if ($syncLocations && $locationIds !== null) {
                $this->locationDistrictSync->syncLocationsForDistrict($district, $locationIds);
            }

            $district->refresh()->load(['locations' => fn ($q) => $q->orderBy('name')]);

            $changes = $this->diffDistrictAuditSnapshots(
                $beforeSnapshot,
                $this->districtAuditSnapshot($district),
                $syncLocations && $locationIds !== null,
            );

            if ($changes !== []) {
                $this->auditLogger->record(
                    AuditEvent::DistrictUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withTarget($district, $district->name)
                        ->withCreatedAt(now())
                );
            }
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Район с таким названием уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Район обновлён']);
    }

    public function destroy(Request $request, District $district)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $district->partner_id !== $partnerId) {
            abort(404);
        }

        if ($district->locations()->exists()) {
            return response()->json([
                'message' => 'Нельзя удалить район: к нему привязаны объекты',
                'errors' => [
                    'district' => ['Нельзя удалить район: к нему привязаны объекты'],
                ],
            ], 422);
        }

        $this->auditLogger->record(
            AuditEvent::DistrictDeleted,
            AuditContext::make("Район удалён: {$district->name}. ID: {$district->id}.")
                ->withTarget($district, $district->name)
                ->withCreatedAt(now())
        );

        $district->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Район удалён',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.districts.index');
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('district');
    }

    /**
     * @return array<string, string>
     */
    private function districtAuditSnapshot(District $district): array
    {
        $locationNames = $district->relationLoaded('locations')
            ? $district->locations->sortBy('name')->pluck('name')->values()->all()
            : [];

        return [
            'name' => (string) ($district->name ?? ''),
            'sort_order' => (string) ($district->sort_order ?? 0),
            'is_enabled' => $district->is_enabled ? 'Да' : 'Нет',
            'locations' => $locationNames !== [] ? implode(', ', $locationNames) : 'не указаны',
        ];
    }

    private function formatDistrictSnapshotDescription(District $district): string
    {
        $snapshot = $this->districtAuditSnapshot($district);

        return implode("\n", [
            "Название: {$snapshot['name']}",
            "Сортировка: {$snapshot['sort_order']}",
            "Активность: {$snapshot['is_enabled']}",
            "Объекты: {$snapshot['locations']}",
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffDistrictAuditSnapshots(array $before, array $after, bool $includeLocations): array
    {
        $labels = [
            'name' => 'Название',
            'sort_order' => 'Сортировка',
            'is_enabled' => 'Активность',
            'locations' => 'Объекты',
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            if ($key === 'locations' && ! $includeLocations) {
                continue;
            }

            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    /**
     * @param  list<string>  $names
     * @return array{locations_label: string, locations_label_full: string, locations_names: list<string>}
     */
    private function formatLocationsLabels(array $names): array
    {
        $names = array_values(array_filter($names, static fn ($name) => trim((string) $name) !== ''));
        $count = count($names);

        if ($count === 0) {
            return [
                'locations_label' => '',
                'locations_label_full' => '',
                'locations_names' => [],
            ];
        }

        $full = implode(', ', $names);

        if ($count <= 2) {
            return [
                'locations_label' => $full,
                'locations_label_full' => $full,
                'locations_names' => $names,
            ];
        }

        return [
            'locations_label' => $names[0] . ', еще ' . ($count - 1) . ' шт.',
            'locations_label_full' => $full,
            'locations_names' => $names,
        ];
    }
}
