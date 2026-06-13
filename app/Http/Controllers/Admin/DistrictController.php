<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\UpdateDistrictRequest;
use App\Models\District;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DistrictController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $this->requirePartnerId();

        return view('admin.districts.index');
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

        $districts = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $districts->map(function (District $district) {
            return [
                'id'                 => $district->id,
                'sort_order'         => $district->sort_order,
                'name'               => $district->name,
                'locations_count'    => (int) $district->locations_count,
                'is_enabled'         => (int) $district->is_enabled,
                'is_enabled_label'   => $district->is_enabled ? 'Да' : 'Нет',
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
        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        try {
            $district = District::create($data);
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

        return response()->json([
            'id' => $district->id,
            'name' => $district->name,
            'sort_order' => $district->sort_order,
            'is_enabled' => (int) $district->is_enabled,
        ]);
    }

    public function update(UpdateDistrictRequest $request, District $district)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $district->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        try {
            $district->update($data);
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

        $district->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Район удалён',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.districts.index');
    }
}
