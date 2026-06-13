<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\District;
use App\Models\Location;
use App\Models\Team;
use App\Services\LocationTeamSyncService;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class LocationController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly LocationTeamSyncService $locationTeamSync,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $teamOptions = auth()->user()?->can('locations.view')
            ? Team::query()
                ->where('partner_id', $partnerId)
                ->orderBy('title')
                ->get(['id', 'title'])
            : collect();

        $districtOptions = auth()->user()?->can('locations.view')
            ? District::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('admin.locations.index', compact('teamOptions', 'districtOptions'));
    }

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'name'        => 'nullable|string',
            'status'      => 'nullable|string',
            'district_id' => 'nullable|string',
            'draw'        => 'nullable|integer',
            'start'       => 'nullable|integer',
            'length'      => 'nullable|integer',
        ]);

        $baseQuery = Location::query()
            ->where('locations.partner_id', $partnerId);

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';
            $baseQuery->where(function ($q) use ($like, $nameSearch) {
                $q->where('name', 'like', $like)
                    ->orWhere('address', 'like', $like)
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

        $districtFilter = trim((string) ($validated['district_id'] ?? ''));
        if ($districtFilter === 'none') {
            $baseQuery->whereNull('district_id');
        } elseif ($districtFilter !== '' && ctype_digit($districtFilter)) {
            $baseQuery->where('district_id', (int) $districtFilter);
        }

        $totalRecords = Location::where('partner_id', $partnerId)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'id':
                $baseQuery->orderBy('id', $orderDir);
                break;
            case 'district_name':
                $baseQuery
                    ->leftJoin('districts as location_districts_sort', 'locations.district_id', '=', 'location_districts_sort.id')
                    ->orderBy('location_districts_sort.name', $orderDir)
                    ->orderBy('locations.name', 'asc')
                    ->select('locations.*');
                break;
            case 'address':
                $baseQuery->orderBy('address', $orderDir)
                    ->orderBy('name', 'asc');
                break;
            case 'is_enabled_label':
                $baseQuery->orderBy('is_enabled', $orderDir)
                    ->orderBy('name', 'asc');
                break;
            case 'name':
            default:
                $baseQuery->orderBy('name', $orderDir)
                    ->orderBy('id', 'asc');
                break;
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        /** @var \App\Models\User|null $filterActor */
        $filterActor = auth()->user();
        $canViewLocationsPivot = $filterActor?->can('locations.view') ?? false;

        $with = ['district'];
        if ($canViewLocationsPivot) {
            $with[] = 'teams';
        }

        $locations = $baseQuery
            ->with($with)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $locations->map(function (Location $location) use ($canViewLocationsPivot) {
            $teamsLabels = [
                'teams_label' => '',
                'teams_label_full' => '',
                'teams_titles' => [],
            ];
            if ($canViewLocationsPivot && $location->relationLoaded('teams')) {
                $teamsLabels = $this->formatTeamsLabels(
                    $location->teams->sortBy('title')->pluck('title')->values()->all()
                );
            }

            return [
                'id'                => $location->id,
                'name'              => $location->name,
                'district_id'       => $location->district_id,
                'district_name'     => $location->district?->name ?? '',
                'address'           => $location->address ?? '',
                'teams_label'       => $teamsLabels['teams_label'],
                'teams_label_full'  => $teamsLabels['teams_label_full'],
                'teams_titles'      => $teamsLabels['teams_titles'],
                'is_enabled'        => (int) $location->is_enabled,
                'is_enabled_label'  => $location->is_enabled ? 'Да' : 'Нет',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StoreLocationRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validated();
        $teamIds = $data['team_ids'] ?? null;
        unset($data['team_ids']);

        if (! $request->user()?->can('locations.view')) {
            $teamIds = null;
        }

        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);
        $data['district_id'] = isset($data['district_id']) ? (int) $data['district_id'] : null;

        try {
            $location = Location::create($data);

            if (is_array($teamIds)) {
                $this->locationTeamSync->syncTeamsForLocation($location, $teamIds);
            }
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Объект с таким названием уже существует в выбранном районе'],
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
                'message' => 'Объект создан',
                'location' => $location,
            ]);
        }

        return redirect()->route('admin.locations.index');
    }

    public function show(Location $location)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $location->partner_id !== $partnerId) {
            abort(404);
        }

        $with = ['district'];
        if (auth()->user()?->can('locations.view')) {
            $with[] = 'teams';
        }

        $location->load($with);

        $payload = [
            'id' => $location->id,
            'name' => $location->name,
            'district_id' => $location->district_id,
            'address' => $location->address,
            'description' => $location->description,
            'is_enabled' => (int) $location->is_enabled,
        ];

        if (auth()->user()?->can('locations.view')) {
            $payload['team_ids'] = $location->teams->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return response()->json($payload);
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $location->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();
        $syncTeams = $request->user()?->can('locations.view') ?? false;
        $teamIds = $syncTeams ? ($data['team_ids'] ?? []) : null;
        unset($data['team_ids']);

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['district_id'] = array_key_exists('district_id', $data)
            ? ($data['district_id'] !== null ? (int) $data['district_id'] : null)
            : $location->district_id;

        try {
            $location->update($data);

            if ($syncTeams) {
                $this->locationTeamSync->syncTeamsForLocation($location, $teamIds);
            }
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Объект с таким названием уже существует в выбранном районе'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Объект обновлён']);
    }

    public function destroy(Request $request, Location $location)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $location->partner_id !== $partnerId) {
            abort(404);
        }

        $location->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Объект удалён',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.locations.index');
    }

    /**
     * @param  list<string>  $titles
     * @return array{teams_label: string, teams_label_full: string, teams_titles: list<string>}
     */
    private function formatTeamsLabels(array $titles): array
    {
        $titles = array_values(array_filter($titles, static fn ($title) => trim((string) $title) !== ''));
        $count = count($titles);

        if ($count === 0) {
            return [
                'teams_label' => '',
                'teams_label_full' => '',
                'teams_titles' => [],
            ];
        }

        $full = implode(', ', $titles);

        if ($count <= 2) {
            return [
                'teams_label' => $full,
                'teams_label_full' => $full,
                'teams_titles' => $titles,
            ];
        }

        return [
            'teams_label' => $titles[0] . ', еще ' . ($count - 1) . ' шт.',
            'teams_label_full' => $full,
            'teams_titles' => $titles,
        ];
    }
}
