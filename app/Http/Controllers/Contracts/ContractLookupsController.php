<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractUserGroupRequest;
use App\Http\Requests\Contracts\ContractUsersSearchRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ContractLookupsController extends Controller
{
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
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
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
                'users.team_id',
                'teams.title as team_title',
            ]);

        $results = $users->map(function ($u) {
            $fullname = trim(($u->lastname ?? '') . ' ' . $u->name);

            return [
                'id'         => $u->id,
                'text'       => $fullname,
                'name'       => $u->name,
                'lastname'   => $u->lastname,
                'team_id'    => $u->team_id,
                'team_title' => $u->team_title,
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
            ->first();

        if (!$student) {
            Log::warning('[contracts.userGroup] student not found or disabled', ['userId' => $userId]);
            return response()->json(['groups' => []]);
        }

        $groups = [];
        if (method_exists($student, 'groups')) {
            $groups = $student->groups()
                ->select('groups.id', 'groups.title')
                ->when(function ($q) {
                    // если есть pivot с флагом активности — оставь; иначе убери строку ниже
                }, function ($q) {
                    $q->wherePivot('is_active', 1);
                })
                ->orderBy('groups.title')
                ->get()
                ->map(fn($g) => ['id' => $g->id, 'title' => $g->title])
                ->values()
                ->all();
        }

        Log::debug('[contracts.userGroup] done', ['groups_count' => count($groups)]);
        return response()->json(['groups' => $groups]);
    }
}

