<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreSportTypeRequest;
use App\Http\Requests\Admin\UpdateSportTypeRequest;
use App\Models\SportType;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SportTypeController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
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

        $data = $request->validated();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        try {
            $sportType->update($data);
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

        $sportType->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Вид спорта удалён',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.sport-types.index');
    }
}
