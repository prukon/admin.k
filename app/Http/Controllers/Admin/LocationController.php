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
        $partnerId = $this->requirePartnerId();

        $locations = Location::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->paginate(50);

        return view('admin.locations.index', compact('locations'));
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
            return response()->json(['message' => 'Локация удалена']);
        }

        return redirect()->route('admin.locations.index');
    }
}

