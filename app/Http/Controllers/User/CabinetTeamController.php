<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AttachCabinetTeamRequest;
use App\Services\TeamUserSyncService;
use App\Services\Users\CabinetTeamAttachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class CabinetTeamController extends Controller
{
    public function attach(
        AttachCabinetTeamRequest $request,
        CabinetTeamAttachService $cabinetTeamAttach,
        TeamUserSyncService $teamUserSync,
    ): JsonResponse|RedirectResponse {
        try {
            $student = $cabinetTeamAttach->attach(
                $request->user(),
                (int) $request->validated('team_id')
            );
        } catch (InvalidArgumentException $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'team_id' => [$e->getMessage()],
                    ],
                ], 422);
            }

            return back()->withErrors(['team_id' => $e->getMessage()]);
        }

        $message = 'Группа добавлена.';
        $teamsLabel = $teamUserSync->teamTitlesLabel($student) ?: '';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'teams_label' => $teamsLabel,
            ]);
        }

        // Safety-net: нативный submit без X-Requested-With — redirect, не «сырой» JSON 200.
        return back()->with('status', $message);
    }
}
