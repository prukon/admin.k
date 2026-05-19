<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolLeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSchoolLeadRequest;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Services\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SchoolLeadController extends Controller
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
    ) {
    }

    public function index(): View
    {
        $partnerId = $this->requirePartnerContext();
        $canViewLocations = Auth::user()?->can('locations.view') ?? false;

        $activeLocations = collect();
        if ($canViewLocations) {
            $activeLocations = Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get();
        }

        $stats = $this->buildPartnerLeadStats($partnerId);

        return view('admin.school-leads', [
            'canViewLocations' => $canViewLocations,
            'activeLocations'  => $activeLocations,
            'leadStats'        => $stats,
        ]);
    }

    public function dataTable(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();
        $canViewLocations = Auth::user()?->can('locations.view') ?? false;

        $baseQuery = SchoolLead::query()
            ->where('school_leads.partner_id', $partnerId)
            ->whereNull('school_leads.deleted_at');

        if ($canViewLocations) {
            $baseQuery->leftJoin('locations', 'locations.id', '=', 'school_leads.location_id')
                ->select('school_leads.*', 'locations.name as location_name');
        }

        $recordsTotal = (clone $baseQuery)->count();
        $query = clone $baseQuery;

        if ($request->has('statuses')) {
            $statuses = array_filter((array) $request->input('statuses', []));
            if (!empty($statuses)) {
                $query->whereIn('school_leads.status', $statuses);
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

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search, $canViewLocations) {
                $q->where('school_leads.name', 'like', "%{$search}%")
                    ->orWhere('school_leads.phone', 'like', "%{$search}%")
                    ->orWhere('school_leads.comment', 'like', "%{$search}%")
                    ->orWhere('school_leads.status', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_source', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_medium', 'like', "%{$search}%")
                    ->orWhere('school_leads.utm_campaign', 'like', "%{$search}%")
                    ->orWhere('school_leads.page_url', 'like', "%{$search}%")
                    ->orWhere('school_leads.referrer', 'like', "%{$search}%")
                    ->orWhereRaw('DATE_FORMAT(school_leads.created_at, "%d.%m.%Y %H:%i") like ?', ["%{$search}%"]);

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
                    'status',
                    'comment',
                    'utm_source',
                    'page_url',
                    'created_at',
                ];

                if (in_array($columnName, $sortable, true)) {
                    $query->orderBy('school_leads.' . $columnName, $dir);
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

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'stats'           => $this->buildPartnerLeadStats($partnerId),
            'data'            => $data->map(function (SchoolLead $item) use ($canViewLocations) {
                $utmParts = array_filter([
                    $item->utm_source ? 'source: ' . $item->utm_source : null,
                    $item->utm_medium ? 'medium: ' . $item->utm_medium : null,
                    $item->utm_campaign ? 'campaign: ' . $item->utm_campaign : null,
                ]);

                $row = [
                    'id'           => $item->id,
                    'name'         => $item->name,
                    'phone'        => $item->phone,
                    'status'       => $item->status?->value,
                    'status_label' => $item->status
                        ? SchoolLeadStatus::label($item->status->value)
                        : null,
                    'comment'      => $item->comment,
                    'utm_summary'  => implode('; ', $utmParts),
                    'page_url'     => $item->page_url,
                    'referrer'     => $item->referrer,
                    'created_at'   => $item->created_at?->format('d.m.Y H:i'),
                ];

                if ($canViewLocations) {
                    $row['location_id']   = $item->location_id;
                    $row['location_name'] = $item->location_name ?? null;
                }

                return $row;
            }),
        ]);
    }

    public function update(UpdateSchoolLeadRequest $request, SchoolLead $schoolLead): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();
        $this->assertLeadBelongsToPartner($schoolLead, $partnerId);

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $schoolLead->status = $data['status']
                ? SchoolLeadStatus::from($data['status'])
                : null;
        }

        if (array_key_exists('comment', $data)) {
            $schoolLead->comment = $data['comment'];
        }

        if (array_key_exists('location_id', $data)) {
            $schoolLead->location_id = $data['location_id'];
        }

        $schoolLead->save();
        $schoolLead->load('location');

        $response = [
            'message'      => 'Изменения сохранены.',
            'status'       => $schoolLead->status?->value,
            'status_label' => $schoolLead->status
                ? SchoolLeadStatus::label($schoolLead->status->value)
                : null,
            'comment'      => $schoolLead->comment,
        ];

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

        $schoolLead->delete();

        return response()->json([
            'message' => 'Заявка удалена.',
        ]);
    }

    /**
     * @return array{total: int, new: int, processing: int}
     */
    private function buildPartnerLeadStats(int $partnerId): array
    {
        return [
            'total'      => (int) $this->partnerLeadsBaseQuery($partnerId)->count(),
            'new'        => $this->countLeadsByStatusForPartner($partnerId, SchoolLeadStatus::New),
            'processing' => $this->countLeadsByStatusForPartner($partnerId, SchoolLeadStatus::Processing),
        ];
    }

    private function partnerLeadsBaseQuery(int $partnerId)
    {
        return SchoolLead::query()
            ->where('school_leads.partner_id', $partnerId)
            ->whereNull('school_leads.deleted_at');
    }

    private function countLeadsByStatusForPartner(int $partnerId, SchoolLeadStatus $status): int
    {
        return (int) $this->partnerLeadsBaseQuery($partnerId)
            ->where('school_leads.status', $status->value)
            ->count();
    }

    private function requirePartnerContext(): int
    {
        $partnerId = $this->partnerContext->partnerId();

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
}
