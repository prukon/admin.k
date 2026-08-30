<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\OnlineUsersMonitorRequest;
use App\Http\Requests\User\OpsMonitorRequest;
use App\Http\Requests\User\UpdateSystemMonitorsRequest;
use App\Support\OnlineUsersMonitor;
use App\Support\OpsMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SystemMonitorsController extends Controller
{
    public function onlineUsers(OnlineUsersMonitorRequest $request): JsonResponse
    {
        $viewer = $request->user();

        return response()->json(OnlineUsersMonitor::snapshot(
            $viewer !== null ? (int) $viewer->id : null
        ));
    }

    public function ops(OpsMonitorRequest $request): JsonResponse
    {
        return response()->json(OpsMonitor::snapshot());
    }

    public function update(UpdateSystemMonitorsRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $enabled = $request->boolean('system_monitors');

        $user->system_monitors = $enabled;
        $user->save();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'system_monitors' => $enabled,
            ]);
        }

        return back()->with(
            'status',
            $enabled
                ? 'Системные мониторы включены.'
                : 'Системные мониторы выключены.'
        );
    }
}
