<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\SchoolLead\FilterRequest;
use App\Http\Requests\UpdateSchoolLeadRequest;
use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Models\Team;
use App\Models\UserField;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use App\Services\SchoolLeads\LatestUserContractLookup;
use App\Support\BuildsLogTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SchoolLeadController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(): View
    {
        $partnerId = $this->requirePartnerContext();
        $canViewLocations = Auth::user()?->can('locations.view') ?? false;
        $canViewDistricts = Auth::user()?->can('districts.view') ?? false;
        $canCreateUserFromLead = Auth::user()?->can('users.view') ?? false;
        $canViewContracts      = Auth::user()?->can('contracts.view') ?? false;

        $activeLocations = collect();
        if ($canViewLocations) {
            $activeLocations = Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get();
        }

        $activeDistricts = collect();
        if ($canViewDistricts) {
            $activeDistricts = District::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $filterTeams = Team::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        $schoolLeadStatuses = $this->statusesForPartner($partnerId);
        $defaultStatusFilterIds = $schoolLeadStatuses
            ->filter(fn (SchoolLeadStatus $status) => $status->is_default_in_filter)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $stats = $this->buildPartnerLeadStats($partnerId);

        $viewData = [
            'canViewLocations'         => $canViewLocations,
            'canViewDistricts'         => $canViewDistricts,
            'canCreateUserFromLead'    => $canCreateUserFromLead,
            'canViewContracts'         => $canViewContracts,
            'activeLocations'          => $activeLocations,
            'activeDistricts'          => $activeDistricts,
            'filterTeams'              => $filterTeams,
            'schoolLeadStatuses'       => $schoolLeadStatuses,
            'defaultStatusFilterIds'   => $defaultStatusFilterIds,
            'leadStats'                => $stats,
        ];

        if ($canCreateUserFromLead) {
            $isSuperadmin = $this->isSuperAdmin();

            $rolesQuery = Role::query()->exceptSuperadmin();
            if (!$isSuperadmin) {
                $rolesQuery->where('is_visible', 1);
            }
            $rolesQuery->where(function ($q) use ($partnerId) {
                $q->where('is_sistem', 1)
                    ->orWhereHas('partners', function ($q2) use ($partnerId) {
                        $q2->where('partner_role.partner_id', $partnerId);
                    });
            });

            $viewData['roles'] = $rolesQuery->orderBy('order_by')->get();
            $viewData['studentRoleId'] = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);
            $viewData['lockStudentRole'] = true;
            $viewData['allTeams'] = Team::query()
                ->where('partner_id', $partnerId)
                ->orderBy('order_by')
                ->get();
            $viewData['userFieldsPayload'] = $this->buildUserFieldsPayloadForCurrentPartner();
        }

        return view('admin.school-leads.index', $viewData + [
            'activeTab' => 'leads',
        ]);
    }

    public function dataTable(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();
        $canViewLocations = Auth::user()?->can('locations.view') ?? false;
        $canViewDistricts = Auth::user()?->can('districts.view') ?? false;
        $canViewContracts = Auth::user()?->can('contracts.view') ?? false;

        $baseQuery = SchoolLead::query()
            ->where('school_leads.partner_id', $partnerId)
            ->whereNull('school_leads.deleted_at')
            ->leftJoin('teams', 'teams.id', '=', 'school_leads.team_id')
            ->leftJoin('school_lead_statuses', 'school_lead_statuses.id', '=', 'school_leads.school_lead_status_id')
            ->select(
                'school_leads.*',
                'teams.title as team_title',
                'school_lead_statuses.name as status_name',
                'school_lead_statuses.color as status_color',
            );

        if ($canViewDistricts) {
            $baseQuery
                ->leftJoin('districts', 'districts.id', '=', 'school_leads.district_id')
                ->addSelect('districts.name as district_name');
        }

        if ($canViewLocations) {
            $baseQuery
                ->leftJoin('locations', 'locations.id', '=', 'school_leads.location_id')
                ->addSelect('locations.name as location_name');
        }

        $recordsTotal = (clone $baseQuery)->count();
        $query = clone $baseQuery;

        if ($request->has('status_ids')) {
            $statusIds = array_values(array_filter(
                array_map('intval', (array) $request->input('status_ids', [])),
                fn (int $id) => $id > 0
            ));

            if ($statusIds !== []) {
                $query->whereIn('school_leads.school_lead_status_id', $statusIds);
            }
        }

        if ($canViewDistricts && $request->filled('district_id')) {
            $districtFilter = $request->input('district_id');
            if ($districtFilter === 'none') {
                $query->whereNull('school_leads.district_id');
            } else {
                $query->where('school_leads.district_id', $districtFilter);
            }
        }

        if ($canViewLocations && $request->filled('location_id')) {
            $locationFilter = $request->input('location_id');
            if ($locationFilter === 'none') {
                $query->whereNull('school_leads.location_id');
            } else {
                $query->where('school_leads.location_id', $locationFilter);
            }
        }

        if ($request->filled('team_id')) {
            $teamFilter = $request->input('team_id');
            if ($teamFilter === 'none') {
                $query->whereNull('school_leads.team_id');
            } else {
                $query->where('school_leads.team_id', $teamFilter);
            }
        }

        if ($request->boolean('has_special_conditions')) {
            $query->where(function ($q) {
                $q->where('school_leads.is_individual_traits', true)
                    ->orWhere('school_leads.is_on_medical_register', true)
                    ->orWhere('school_leads.is_with_disability', true);
            });
        }

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search, $canViewLocations, $canViewDistricts) {
                $q->where('school_leads.name', 'like', "%{$search}%")
                    ->orWhere('school_leads.phone', 'like', "%{$search}%")
                    ->orWhere('school_leads.parent_lastname', 'like', "%{$search}%")
                    ->orWhere('school_leads.parent_firstname', 'like', "%{$search}%")
                    ->orWhere('school_leads.parent_middlename', 'like', "%{$search}%")
                    ->orWhere('school_leads.parent_phone', 'like', "%{$search}%")
                    ->orWhere('school_leads.parent_email', 'like', "%{$search}%")
                    ->orWhere('school_leads.child_lastname', 'like', "%{$search}%")
                    ->orWhere('school_leads.child_firstname', 'like', "%{$search}%")
                    ->orWhere('school_leads.child_middlename', 'like', "%{$search}%")
                    ->orWhere('school_leads.comment', 'like', "%{$search}%")
                    ->orWhere('school_lead_statuses.name', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_source', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_medium', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_campaign', 'like', "%{$search}%")
                    ->orWhere('school_leads.page_url', 'like', "%{$search}%")
                    ->orWhere('school_leads.referrer', 'like', "%{$search}%")
                    ->orWhere('teams.title', 'like', "%{$search}%")
                    ->orWhereRaw('DATE_FORMAT(school_leads.created_at, "%d.%m.%Y %H:%i") like ?', ["%{$search}%"])
                    ->orWhereRaw('DATE_FORMAT(school_leads.child_birthday, "%d.%m.%Y") like ?', ["%{$search}%"]);

                if ($canViewDistricts) {
                    $q->orWhere('districts.name', 'like', "%{$search}%");
                }

                if ($canViewLocations) {
                    $q->orWhere('locations.name', 'like', "%{$search}%");
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        $order   = $request->input('order', []);
        $columns = $request->input('columns', []);

        if ($order && $columns) {
            foreach ($order as $ord) {
                $columnIdx  = $ord['column'];
                $columnName = $columns[$columnIdx]['data'] ?? null;
                $dir        = ($ord['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

                $sortable = [
                    'id',
                    'name',
                    'phone',
                    'parent_email',
                    'child_birthday',
                    'comment',
                    'utm_source',
                    'page_url',
                    'created_at',
                ];

                if (in_array($columnName, $sortable, true)) {
                    $query->orderBy('school_leads.' . $columnName, $dir);
                } elseif ($columnName === 'status_label') {
                    $query->orderBy('school_lead_statuses.name', $dir);
                } elseif ($columnName === 'team_title') {
                    $query->orderBy('teams.title', $dir);
                } elseif ($canViewDistricts && $columnName === 'district_name') {
                    $query->orderBy('districts.name', $dir);
                } elseif ($canViewLocations && $columnName === 'location_name') {
                    $query->orderBy('locations.name', $dir);
                }
            }
        } else {
            $query->orderByDesc('school_leads.created_at');
        }

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);

        $data = $query->skip($start)->take($length)->get();

        $latestContractsByUser = collect();
        if ($canViewContracts) {
            $userIds = $data->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $latestContractsByUser = app(LatestUserContractLookup::class)
                ->forUserIds($partnerId, $userIds);
        }

        $contractLookup = app(LatestUserContractLookup::class);

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'stats'           => $this->buildPartnerLeadStats($partnerId),
            'data'            => $data->map(function (SchoolLead $item) use (
                $canViewLocations,
                $canViewDistricts,
                $canViewContracts,
                $latestContractsByUser,
                $contractLookup
            ) {
                $utmParts = array_filter([
                    $item->utm_source ? 'source: ' . $item->utm_source : null,
                    $item->utm_medium ? 'medium: ' . $item->utm_medium : null,
                    $item->utm_campaign ? 'campaign: ' . $item->utm_campaign : null,
                ]);

                $parentFullName = $item->parent_full_name !== ''
                    ? $item->parent_full_name
                    : $item->name;
                $parentPhone = $item->parent_phone ?: $item->phone;
                $childFullName = $item->child_full_name !== ''
                    ? $item->child_full_name
                    : null;
                $parentNameParts = $item->resolvedParentNameParts();
                $childNameParts = $item->resolvedChildNameParts();
                $statusPayload = $this->schoolLeadStatusPayload($item);

                $row = [
                    'id'                     => $item->id,
                    'name'                   => $item->name,
                    'phone'                  => $item->phone,
                    'parent_full_name'       => $parentFullName,
                    'parent_phone'           => $parentPhone,
                    'parent_email'           => $item->parent_email,
                    'parent_lastname'        => $parentNameParts['lastname'] ?: null,
                    'parent_firstname'       => $parentNameParts['firstname'] ?: null,
                    'parent_middlename'      => $parentNameParts['middlename'] ?: null,
                    'child_full_name'        => $childFullName,
                    'child_lastname'         => $childNameParts['lastname'] ?: null,
                    'child_firstname'        => $childNameParts['firstname'] ?: null,
                    'child_middlename'       => $childNameParts['middlename'] ?: null,
                    'child_birthday'         => $item->child_birthday?->format('d.m.Y'),
                    'child_birthday_iso'     => $item->child_birthday?->format('Y-m-d'),
                    'team_id'                => $item->team_id,
                    'team_title'             => $item->team_title,
                    'is_individual_traits'   => (bool) $item->is_individual_traits,
                    'is_on_medical_register' => (bool) $item->is_on_medical_register,
                    'is_with_disability'     => (bool) $item->is_with_disability,
                    'user_id'                => $item->user_id,
                    'comment'                => $item->comment,
                    'utm_summary'            => implode('; ', $utmParts),
                    'page_url'               => $item->page_url,
                    'referrer'               => $item->referrer,
                    'created_at'             => $item->created_at?->format('d.m.Y H:i'),
                ] + $statusPayload;

                if ($canViewDistricts) {
                    $row['district_id']   = $item->district_id;
                    $row['district_name'] = $item->district_name ?? null;
                }

                if ($canViewLocations) {
                    $row['location_id']   = $item->location_id;
                    $row['location_name'] = $item->location_name ?? null;
                }

                if ($canViewContracts && $item->user_id) {
                    $contract = $latestContractsByUser->get((int) $item->user_id);

                    if ($contract) {
                        $row['latest_contract'] = [
                            'id'    => $contract->id,
                            'label' => $contractLookup->formatActionLabel($contract),
                            'url'   => route('contracts.show', $contract->id),
                        ];
                    } else {
                        $row['create_contract_url'] = route('contracts.index', [
                            'user_id' => $item->user_id,
                        ]);
                    }
                }

                return $row;
            }),
        ]);
    }

    public function update(UpdateSchoolLeadRequest $request, SchoolLead $schoolLead): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();
        $this->assertLeadBelongsToPartner($schoolLead, $partnerId);

        $schoolLead->load('district', 'location', 'schoolLeadStatus');
        $beforeSnapshot = $this->schoolLeadAuditSnapshot($schoolLead);

        $data = $request->validated();

        if (array_key_exists('school_lead_status_id', $data)) {
            $schoolLead->school_lead_status_id = $data['school_lead_status_id'];
        }

        if (array_key_exists('comment', $data)) {
            $schoolLead->comment = $data['comment'];
        }

        if (array_key_exists('district_id', $data)) {
            $schoolLead->district_id = $data['district_id'];
        }

        if (array_key_exists('location_id', $data)) {
            $schoolLead->location_id = $data['location_id'];
        }

        $schoolLead->save();
        $schoolLead->load('district', 'location', 'schoolLeadStatus');

        $changes = $this->diffSchoolLeadAuditSnapshots(
            $beforeSnapshot,
            $this->schoolLeadAuditSnapshot($schoolLead),
        );

        if ($changes !== []) {
            $this->auditLogger->record(
                AuditEvent::SchoolLeadUpdated,
                AuditContext::make(implode("\n", $changes))
                    ->withTarget($schoolLead, $this->schoolLeadAuditLabel($schoolLead))
                    ->withCreatedAt(now())
            );
        }

        $response = [
            'message' => 'Изменения сохранены.',
            'comment' => $schoolLead->comment,
        ] + $this->schoolLeadStatusPayload($schoolLead);

        if ($request->user()?->can('districts.view')) {
            $response['district_id']   = $schoolLead->district_id;
            $response['district_name'] = $schoolLead->district?->name;
        }

        if ($request->user()?->can('locations.view')) {
            $response['location_id']   = $schoolLead->location_id;
            $response['location_name'] = $schoolLead->location?->name;
        }

        return response()->json($response);
    }

    public function destroy(SchoolLead $schoolLead): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();
        $this->assertLeadBelongsToPartner($schoolLead, $partnerId);

        $this->auditLogger->record(
            AuditEvent::SchoolLeadDeleted,
            AuditContext::make("Заявка удалена: {$this->schoolLeadAuditLabel($schoolLead)}.")
                ->withTarget($schoolLead, $this->schoolLeadAuditLabel($schoolLead))
                ->withCreatedAt(now())
        );

        $schoolLead->delete();

        return response()->json([
            'message' => 'Заявка удалена.',
        ]);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('school_lead');
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,field_type:string,roles:array<int,int>,editable:bool}>
     */
    private function buildUserFieldsPayloadForCurrentPartner(): array
    {
        $partnerId = $this->partnerId();
        $currentUser = $this->currentUser();
        $isSuperadmin = $this->isSuperAdmin();

        $fieldsQuery = UserField::with('roles')
            ->where('partner_id', $partnerId);

        if (!$isSuperadmin && $currentUser?->role_id) {
            $fieldsQuery->whereHas('roles', fn ($q) =>
                $q->where('role_id', $currentUser->role_id)
            );
        }

        $fields = $fieldsQuery->get();

        return $fields->map(function (UserField $f) use ($currentUser, $isSuperadmin) {
            $allowedRoles = $f->roles->pluck('id')->map(fn ($i) => (int) $i);

            return [
                'id'         => $f->id,
                'name'       => $f->name,
                'slug'       => $f->slug,
                'field_type' => $f->field_type,
                'roles'      => $allowedRoles->all(),
                'editable'   => $isSuperadmin || $allowedRoles->contains($currentUser?->role_id),
            ];
        })->all();
    }

    /**
     * @return array{total: int, new: int}
     */
    private function buildPartnerLeadStats(int $partnerId): array
    {
        return [
            'total' => (int) $this->partnerLeadsBaseQuery($partnerId)->count(),
            'new'   => (int) $this->partnerLeadsBaseQuery($partnerId)
                ->where('school_leads.school_lead_status_id', SchoolLeadStatus::systemNewId())
                ->count(),
        ];
    }

    private function partnerLeadsBaseQuery(int $partnerId)
    {
        return SchoolLead::query()
            ->where('school_leads.partner_id', $partnerId)
            ->whereNull('school_leads.deleted_at');
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolLeadStatus>
     */
    private function statusesForPartner(int $partnerId)
    {
        return SchoolLeadStatus::query()
            ->availableForPartner($partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolLeadStatusPayload(SchoolLead $schoolLead): array
    {
        $status = $schoolLead->relationLoaded('schoolLeadStatus')
            ? $schoolLead->schoolLeadStatus
            : null;

        if (!$status && $schoolLead->school_lead_status_id) {
            $statusName = $schoolLead->status_name ?? null;
            $statusColor = $schoolLead->status_color ?? null;

            if ($statusName !== null) {
                $status = new SchoolLeadStatus([
                    'id'    => $schoolLead->school_lead_status_id,
                    'name'  => $statusName,
                    'color' => $statusColor,
                ]);
            }
        }

        return [
            'school_lead_status_id' => $schoolLead->school_lead_status_id,
            'status_label'          => $status?->name,
            'status_color'          => $status?->color,
            'status_text_color'     => $status?->contrastingTextColor(),
            'status_badge_style'    => $status?->badgeStyle(),
        ];
    }

    private function requirePartnerContext(): int
    {
        $partnerId = $this->partnerId();

        if (!$partnerId) {
            abort(403, 'Партнёр не выбран.');
        }

        return (int) $partnerId;
    }

    private function assertLeadBelongsToPartner(SchoolLead $schoolLead, int $partnerId): void
    {
        if ((int) $schoolLead->partner_id !== $partnerId) {
            abort(404);
        }
    }

    private function schoolLeadAuditLabel(SchoolLead $schoolLead): string
    {
        $name = trim($schoolLead->parent_full_name) !== ''
            ? trim($schoolLead->parent_full_name)
            : trim((string) ($schoolLead->name ?? ''));

        return $name !== ''
            ? "Заявка #{$schoolLead->id}: {$name}"
            : "Заявка #{$schoolLead->id}";
    }

    /**
     * @return array<string, string>
     */
    private function schoolLeadAuditSnapshot(SchoolLead $schoolLead): array
    {
        $statusName = $schoolLead->schoolLeadStatus?->name
            ?? $schoolLead->status_name
            ?? null;

        return [
            'status'   => $statusName ?: 'не указан',
            'comment'  => $this->auditTextValue($schoolLead->comment, 'не указан'),
            'district' => $schoolLead->district?->name ?? 'не указан',
            'location' => $schoolLead->location?->name ?? 'не указан',
        ];
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffSchoolLeadAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'status'   => 'Статус',
            'comment'  => 'Комментарий',
            'district' => 'Район',
            'location' => 'Объект',
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    private function auditTextValue(mixed $value, string $emptyLabel): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $emptyLabel;
    }
}
