<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MyGroupController extends Controller
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    /**
     * Страница "Моя группа"
     */
    public function index()
    {
        return view('user.myGroup');
    }

    /**
     * AJAX-данные для визуализации
     */
    public function data(Request $request)
    {
        $me = Auth::user();

        if (!$me) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не авторизован.',
            ]);
        }

        $me->load('teams');

        $teamIds = $this->teamUserSync->teamIdsForStudent($me);
        if ($teamIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не состоит в группе.',
            ]);
        }

        $requestedTeamId = $request->integer('team_id');
        $activeTeamId = in_array($requestedTeamId, $teamIds, true)
            ? $requestedTeamId
            : $teamIds[0];

        $teamsForSelect = $me->teams
            ->map(fn ($team) => ['id' => (int) $team->id, 'title' => (string) $team->title])
            ->values();

        $current = [
            'id'     => $me->id,
            'name'   => $me->name,
            'avatar' => $this->avatarUrl($me->image_crop, $me->image),
        ];

        $peers = User::query()
            ->where('partner_id', $me->partner_id)
            ->where('is_enabled', 1)
            ->where('id', '!=', $me->id)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $activeTeamId))
            ->inRandomOrder()
            ->get(['id', 'name', 'image', 'image_crop']);

        $list = $peers->map(function ($u) {
            return [
                'id'     => $u->id,
                'name'   => $u->name,
                'avatar' => $this->avatarUrl($u->image_crop, $u->image),
            ];
        })->values();

        return response()->json([
            'success'         => true,
            'current'         => $current,
            'peers'           => $list,
            'active_team_id'  => $activeTeamId,
            'teams'           => $teamsForSelect,
        ]);
    }

    private function avatarUrl(?string $imageCrop, ?string $imageFallback = null): string
    {
        $raw = $imageCrop ?: $imageFallback;

        if (!$raw) {
            return asset('img/default-avatar.png');
        }

        if (preg_match('~^https?://~i', $raw)) {
            return $raw;
        }

        $filename = basename($raw);
        $path = 'avatars/' . ltrim($filename, '/');

        if (!Storage::disk('public')->exists($path)) {
            return asset('img/default-avatar.png');
        }

        return Storage::url($path);
    }
}
