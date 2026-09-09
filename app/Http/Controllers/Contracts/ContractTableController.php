<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractsColumnsSettingsSaveRequest;
use App\Http\Requests\Contracts\ContractsDataRequest;
use App\Models\Contract;
use App\Models\Team;
use App\Models\UserTableSetting;
use App\Services\Contracts\ContractPathTimelineBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractTableController extends Controller
{
    public function __construct(
        private readonly ContractPathTimelineBuilder $pathTimelineBuilder,
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

        $baseQuery->addSelect(DB::raw($this->lastEventAtSubquery() . ' as last_event_at'));

        // --- сортировка DataTables ---
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

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
                    $baseQuery->orderByDesc('contracts.id');
                    break;
                case 8:
                    $baseQuery->orderByRaw('last_event_at is null, last_event_at ' . $orderDir);
                    break;
                case 9:
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
                'status_label'       => $contract->school_status_ru ?? '',
                'status_badge_class' => $contract->status_badge_class ?? '',
                'status'              => $contract->status,
                'creation_mode'       => $contract->creation_mode,
                'path_title'          => $this->pathTimelineBuilder->title($contract),
                'path_steps'          => $this->pathTimelineBuilder->build($contract),
                'download_signed_url' => $this->signedDownloadUrl($contract),
                'updated_at'          => $this->formatLastEventAt($contract->last_event_at ?? null),
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

    /**
     * Дата последнего события журнала этого договора (как на карточке: max id).
     */
    private function lastEventAtSubquery(): string
    {
        return '(select last_ce.created_at from contract_events as last_ce where last_ce.contract_id = contracts.id order by last_ce.id desc limit 1)';
    }

    private function formatLastEventAt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d.m.Y H:i:s');
    }

    private function signedDownloadUrl(Contract $contract): ?string
    {
        if (!filled($contract->signed_pdf_path)) {
            return null;
        }

        return route('contracts.downloadSigned', $contract);
    }
}

