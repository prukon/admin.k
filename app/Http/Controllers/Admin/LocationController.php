<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class LocationController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $this->requirePartnerId();

        return view('admin.locations.index');
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

        $baseQuery = Location::query()
            ->where('partner_id', $partnerId);

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

        $locations = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $locations->map(function (Location $location) {
            return [
                'id'                => $location->id,
                'name'              => $location->name,
                'address'           => $location->address ?? '',
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
        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);

        try {
            $location = Location::create($data);
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Локация с таким названием уже существует'],
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
                'message' => 'Локация создана',
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

        return response()->json([
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'description' => $location->description,
            'is_enabled' => (int) $location->is_enabled,
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $location->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        try {
            $location->update($data);
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'name' => ['Локация с таким названием уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Локация обновлена']);
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
                'message' => 'Локация удалена',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.locations.index');
    }
}
