<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractUserGroupRequest;
use App\Http\Requests\Contracts\ContractUsersSearchRequest;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractLookupsController extends Controller
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    // единая точка входа
    private function partner(): \App\Models\Partner
    {
        $p = app('current_partner');
        abort_unless($p, 403, 'Партнёр не выбран.');
        return $p;
    }

    private function partnerId(): int
    {
        return $this->partner()->id;
    }

    // ------- AJAX: поиск учеников текущего партнёра для Select2 -------
    public function usersSearch(ContractUsersSearchRequest $request)
    {
        $q = (string)($request->validated()['q'] ?? '');
        $partnerId = $this->partnerId();

        $users = User::query()
            ->when($partnerId, fn($qq) => $qq->where('users.partner_id', $partnerId))
            ->where('users.is_enabled', 1)
            ->with(['teams' => fn ($query) => $query->where('teams.partner_id', $partnerId)])
            ->leftJoin('parents', function ($join) {
                $join->on('parents.id', '=', 'users.parent_id')
                    ->whereNull('parents.deleted_at');
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('users.name', 'like', "%{$q}%")
                        ->orWhere('users.lastname', 'like', "%{$q}%")
                        ->orWhere('users.phone', 'like', "%{$q}%")
                        ->orWhere('users.email', 'like', "%{$q}%");
                });
            })
            ->orderBy('users.lastname')
            ->orderBy('users.name')
            ->limit(50)
            ->get([
                'users.id',
                'users.name',
                'users.lastname',
                DB::raw("TRIM(CONCAT_WS(' ', parents.lastname, parents.firstname, parents.middlename)) as parent_full_name_from_join"),
            ]);

        $results = $users->map(function ($u) {
            $fullname = trim(($u->lastname ?? '') . ' ' . $u->name);
            $parentFullName = trim((string) ($u->getAttributes()['parent_full_name_from_join'] ?? ''));
            $teamIds = $this->teamUserSync->teamIdsForStudent($u);
            $teamTitle = $this->teamUserSync->teamTitlesLabel($u);
            $firstTeamId = $teamIds[0] ?? null;
            $groups = $u->teams
                ->map(fn ($team) => ['id' => (int) $team->id, 'title' => (string) $team->title])
                ->values()
                ->all();

            return [
                'id'               => $u->id,
                'text'             => $fullname,
                'name'             => $u->name,
                'lastname'         => $u->lastname,
                'team_id'          => $firstTeamId,
                'team_ids'         => $teamIds,
                'team_title'       => $teamTitle !== '' ? $teamTitle : null,
                'groups'           => $groups,
                'parent_full_name' => $parentFullName !== '' ? $parentFullName : null,
            ];
        });

        return response()->json(['results' => $results]);
    }

    // ------- AJAX: вернуть группу(ы) выбранного ученика -------
    public function userGroup(ContractUserGroupRequest $request)
    {
        $userId = (int)$request->validated()['user_id'];
        $partnerId = $this->partnerId();

        $student = User::query()
            ->where('id', $userId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->with(['teams' => fn ($query) => $query->where('teams.partner_id', $partnerId)])
            ->first();

        if (!$student) {
            Log::warning('[contracts.userGroup] student not found or disabled', ['userId' => $userId]);
            return response()->json(['groups' => []]);
        }

        $groups = $student->teams
            ->map(fn ($team) => ['id' => (int) $team->id, 'title' => (string) $team->title])
            ->values()
            ->all();

        Log::debug('[contracts.userGroup] done', ['groups_count' => count($groups)]);

        return response()->json(['groups' => $groups]);
    }
}
