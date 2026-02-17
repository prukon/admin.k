<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractsColumnsSettingsSaveRequest;
use App\Http\Requests\Contracts\ContractsDataRequest;
use App\Models\Contract;
use App\Models\Team;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class ContractTableController extends Controller
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

    /**
     * DataTables серверный endpoint для списка договоров.
     * Возвращает JSON в формате, понятном DataTables.
     */
    public function data(ContractsDataRequest $request)
    {
        $partnerId = $this->partnerId();
        $validated = $request->validated();

        $statusFilter = $validated['status'] ?? null;
        $groupFilter  = $validated['group_id'] ?? null;
        $searchValue  = $validated['search_value'] ?? null;

        // Базовый запрос по партнёру
        $baseQuery = Contract::query()
            ->where('contracts.school_id', $partnerId)
            ->leftJoin('users', 'users.id', '=', 'contracts.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'contracts.group_id')
            ->select([
                'contracts.*',
                'users.name as user_name',
                'users.lastname as user_lastname',
                'users.phone as user_phone',
                'users.email as user_email',
                'teams.title as team_title',
            ]);

        if (!empty($statusFilter)) {
            $baseQuery->where('contracts.status', $statusFilter);
        }

        if ($groupFilter !== null && $groupFilter !== '') {
            if ($groupFilter === 'none') {
                $baseQuery->whereNull('contracts.group_id');
            } else {
                $baseQuery->where('contracts.group_id', $groupFilter);
            }
        }

        if (!empty($searchValue)) {
            $like = '%' . $searchValue . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('users.email', 'like', $like);
            });
        }

        $totalRecords = Contract::where('school_id', $partnerId)->count();

        $filteredQuery = clone $baseQuery;
        $recordsFiltered = $filteredQuery->count();

        // --- сортировка DataTables ---
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0:
                    $baseQuery->orderByDesc('contracts.id');
                    break;
                case 1:
                    $baseQuery->orderBy('users.name', $orderDir);
                    break;
                case 2:
                    $baseQuery->orderBy('users.lastname', $orderDir);
                    break;
                case 3:
                    $baseQuery->orderBy('teams.title', $orderDir);
                    break;
                case 4:
                    $baseQuery->orderBy('users.phone', $orderDir);
                    break;
                case 5:
                    $baseQuery->orderBy('users.email', $orderDir);
                    break;
                case 6:
                    $baseQuery->orderBy('contracts.status', $orderDir);
                    break;
                case 7:
                    $baseQuery->orderBy('contracts.updated_at', $orderDir);
                    break;
                case 8:
                default:
                    $baseQuery->orderByDesc('contracts.id');
                    break;
            }
        } else {
            $baseQuery->orderByDesc('contracts.id');
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        $contracts = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $contracts->map(function (Contract $contract) {
            return [
                'id'                 => $contract->id,
                'user_name'          => $contract->user_name ?: '—',
                'user_lastname'      => $contract->user_lastname ?: '—',
                'team_title'         => $contract->team_title ?: '—',
                'user_phone'         => $contract->user_phone ?: '—',
                'user_email'         => $contract->user_email ?: '—',
                'status_label'       => $contract->status_ru ?? '',
                'status_badge_class' => $contract->status_badge_class ?? '',
                'updated_at'         => $contract->updated_at
                    ? $contract->updated_at->format('d.m.Y H:i:s')
                    : '',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int)($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Вернуть настройки колонок для текущего пользователя для таблицы "contracts_index".
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();
        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'contracts_index')
            ->first();

        $columns = $settings?->columns;
        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя для таблицы "contracts_index".
     */
    public function saveColumnsSettings(ContractsColumnsSettingsSaveRequest $request)
    {
        $userId = Auth::id();
        $validated = $request->validated();

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'contracts_index',
            ],
            [
                'columns' => $validated['columns'],
            ]
        );

        return response()->json(['success' => true]);
    }
}

