<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolLeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSchoolLeadRequest;
use App\Models\SchoolLead;
use App\Services\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolLeadController extends Controller
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
    ) {
    }

    public function index(): View
    {
        $this->requirePartnerContext();

        return view('admin.school-leads');
    }

    public function dataTable(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerContext();

        $baseQuery = SchoolLead::query()
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at');

        $recordsTotal = (clone $baseQuery)->count();
        $query = clone $baseQuery;

        if ($request->has('statuses')) {
            $statuses = array_filter((array) $request->input('statuses', []));
            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('utm_source', 'like', "%{$search}%")
                    ->orWhere('utm_medium', 'like', "%{$search}%")
                    ->orWhere('utm_campaign', 'like', "%{$search}%")
                    ->orWhere('page_url', 'like', "%{$search}%")
                    ->orWhere('referrer', 'like', "%{$search}%")
                    ->orWhereRaw('DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") like ?', ["%{$search}%"]);
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
                    $query->orderBy($columnName, $dir);
                }
            }
        } else {
            $query->orderByDesc('created_at');
        }

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);

        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->map(function (SchoolLead $item) {
                $utmParts = array_filter([
                    $item->utm_source ? 'source: ' . $item->utm_source : null,
                    $item->utm_medium ? 'medium: ' . $item->utm_medium : null,
                    $item->utm_campaign ? 'campaign: ' . $item->utm_campaign : null,
                ]);

                return [
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

        $schoolLead->save();

        return response()->json([
            'message'      => 'Изменения сохранены.',
            'status'       => $schoolLead->status?->value,
            'status_label' => $schoolLead->status
                ? SchoolLeadStatus::label($schoolLead->status->value)
                : null,
            'comment'      => $schoolLead->comment,
        ]);
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
