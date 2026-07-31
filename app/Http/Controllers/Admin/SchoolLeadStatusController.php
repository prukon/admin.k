<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreSchoolLeadStatusRequest;
use App\Http\Requests\Admin\UpdateSchoolLeadStatusRequest;
use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolLeadStatusController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $statuses = SchoolLeadStatus::query()
            ->availableForPartner($partnerId)
            ->withCount('schoolLeads')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SchoolLeadStatus $status) => $status->toFrontendArray((int) $status->school_leads_count))
            ->values()
            ->all();

        return response()->json([
            'statuses' => $statuses,
        ]);
    }

    public function store(StoreSchoolLeadStatusRequest $request): JsonResponse|RedirectResponse
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();
        $data = $request->validated();

        $sortOrder = isset($data['sort_order'])
            ? (int) $data['sort_order']
            : $this->nextCustomSortOrder($partnerId);

        $status = null;

        DB::transaction(function () use ($authorId, $data, $partnerId, $sortOrder, &$status) {
            $status = new SchoolLeadStatus();
            $status->partner_id = $partnerId;
            $status->name = $data['name'];
            $status->color = $data['color'] ?? null;
            $status->sort_order = $sortOrder;
            $status->is_default_in_filter = (bool) ($data['is_default_in_filter'] ?? false);
            $status->is_system = false;
            $status->save();

            $this->auditLogger->record(
                AuditEvent::SchoolLeadStatusCreated,
                AuditContext::make("Создание статуса заявки: {$status->name}, ID: {$status->id}")
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        $payload = [
            'success' => true,
            'status'  => $status?->toFrontendArray(0),
            'message' => 'Статус создан.',
        ];

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.school-leads')
            ->with('success', (string) $payload['message']);
    }

    public function update(UpdateSchoolLeadStatusRequest $request, int $schoolLeadStatus): JsonResponse|RedirectResponse
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();

        $status = SchoolLeadStatus::query()->whereKey($schoolLeadStatus)->firstOrFail();

        if ($status->is_system) {
            return $this->statusMutationDenied(
                $request,
                'Системные статусы нельзя редактировать.',
                403
            );
        }

        if ((int) $status->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($authorId, $data, $status, $partnerId) {
            $before = $status->only(['name', 'color', 'sort_order', 'is_default_in_filter']);

            $status->update([
                'name'                 => $data['name'],
                'color'                => $data['color'] ?? null,
                'sort_order'           => (int) ($data['sort_order'] ?? $status->sort_order),
                'is_default_in_filter' => (bool) ($data['is_default_in_filter'] ?? false),
            ]);

            $after = $status->only(['name', 'color', 'sort_order', 'is_default_in_filter']);

            $this->auditLogger->record(
                AuditEvent::SchoolLeadStatusUpdated,
                AuditContext::make(
                    "Обновление статуса заявки: {$status->name}, ID: {$status->id}.\n"
                    . 'Было: ' . json_encode($before, JSON_UNESCAPED_UNICODE) . "\n"
                    . 'Стало: ' . json_encode($after, JSON_UNESCAPED_UNICODE)
                )
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        $status->loadCount('schoolLeads');

        $payload = [
            'success' => true,
            'status'  => $status->toFrontendArray((int) $status->school_leads_count),
            'message' => 'Статус обновлён.',
        ];

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.school-leads')
            ->with('success', (string) $payload['message']);
    }

    public function destroy(Request $request, int $schoolLeadStatus): JsonResponse|RedirectResponse
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();

        $status = SchoolLeadStatus::query()->whereKey($schoolLeadStatus)->firstOrFail();

        if ($status->is_system) {
            return $this->statusMutationDenied(
                $request,
                'Системные статусы нельзя удалять.',
                403
            );
        }

        if ((int) $status->partner_id !== $partnerId) {
            abort(404);
        }

        $leadsCount = SchoolLead::query()
            ->where('partner_id', $partnerId)
            ->where('school_lead_status_id', $status->id)
            ->whereNull('deleted_at')
            ->count();

        if ($leadsCount > 0) {
            $message = 'Нельзя удалить статус: на нём есть заявки.';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors'  => [
                        'status' => [$message],
                    ],
                ], 422);
            }

            return redirect()
                ->route('admin.school-leads')
                ->withErrors(['status' => $message]);
        }

        DB::transaction(function () use ($authorId, $status, $partnerId) {
            $status->delete();

            $this->auditLogger->record(
                AuditEvent::SchoolLeadStatusDeleted,
                AuditContext::make("Удаление статуса заявки: {$status->name}, ID: {$status->id}")
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        $payload = [
            'success' => true,
            'message' => 'Статус удалён.',
        ];

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.school-leads')
            ->with('success', (string) $payload['message']);
    }

    private function statusMutationDenied(
        Request $request,
        string $message,
        int $statusCode
    ): JsonResponse|RedirectResponse {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], $statusCode);
        }

        return redirect()
            ->route('admin.school-leads')
            ->withErrors(['status' => $message]);
    }

    private function nextCustomSortOrder(int $partnerId): int
    {
        $max = SchoolLeadStatus::query()
            ->where('partner_id', $partnerId)
            ->max('sort_order');

        return ((int) $max) + 10;
    }
}
