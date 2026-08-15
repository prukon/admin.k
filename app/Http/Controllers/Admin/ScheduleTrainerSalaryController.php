<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\FormTrainerSalarySnapshotRequest;
use App\Http\Requests\Admin\TrainerSalaryReportRequest;
use App\Http\Requests\Admin\UpdateTrainerSalaryDraftLineRequest;
use App\Models\TrainerProfile;
use App\Services\PartnerContext;
use App\Services\Schedule\TrainerSalaryService;
use App\Support\TrainerSalaryAccess;
use App\Support\TrainerTypeAccess;
use Illuminate\Http\JsonResponse;

class ScheduleTrainerSalaryController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TrainerSalaryService $salaryService,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(TrainerSalaryReportRequest $request)
    {
        $payload = $this->buildPagePayload($request);

        return view('admin.schedule.index', array_merge($payload, [
            'activeTab' => 'trainer-salary',
            'canManageTrainerSalary' => TrainerSalaryAccess::canManageModule($request->user()),
        ]));
    }

    public function data(TrainerSalaryReportRequest $request): JsonResponse
    {
        return response()->json($this->buildPagePayload($request));
    }

    public function updateDraft(
        UpdateTrainerSalaryDraftLineRequest $request,
        TrainerProfile $trainerProfile,
    ): JsonResponse {
        $partnerId = $this->requirePartnerId();
        [$year, $month] = [(int) $request->input('year'), (int) $request->input('month')];

        $period = $this->salaryService->ensurePeriod($partnerId, $year, $month);

        $row = $this->salaryService->updateDraftLine(
            $period,
            $trainerProfile,
            $partnerId,
            $request->draftPayload(),
        );

        $scheme = $this->salaryService->schemeForPeriod($period);
        $canManage = TrainerSalaryAccess::canManageModule($request->user());
        $payload = [
            'message' => 'Черновик сохранён',
            'row' => $row,
        ];

        if ($scheme->prefersFullTableReload()) {
            $page = $this->composeDraftPage($partnerId, $year, $month, $canManage);
            $payload['reload_table'] = true;
            $payload['table_html'] = $page['table_html'];
        }

        return response()->json($payload);
    }

    public function formOne(
        FormTrainerSalarySnapshotRequest $request,
        TrainerProfile $trainerProfile,
    ): JsonResponse {
        $partnerId = $this->requirePartnerId();
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }

        [$year, $month] = [(int) $request->input('year'), (int) $request->input('month')];
        $period = $this->salaryService->ensurePeriod($partnerId, $year, $month);

        $result = $this->salaryService->formSnapshotForTrainer($period, $trainerProfile, $partnerId, $actor);
        $scheme = $this->salaryService->schemeForPeriod($period);
        $canManage = TrainerSalaryAccess::canManageModule($request->user());

        $payload = [
            'message' => 'Слепок ЗП сформирован (версия ' . ($result['snapshot']['version'] ?? '') . ')',
            'snapshot' => $result['snapshot'],
            'row' => $result['row'],
        ];

        if ($scheme->prefersFullTableReload()) {
            $page = $this->composeDraftPage($partnerId, $year, $month, $canManage);
            $payload['reload_table'] = true;
            $payload['table_html'] = $page['table_html'];
        }

        return response()->json($payload);
    }

    public function formAll(FormTrainerSalarySnapshotRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }

        [$year, $month] = [(int) $request->input('year'), (int) $request->input('month')];
        $period = $this->salaryService->ensurePeriod($partnerId, $year, $month);

        $result = $this->salaryService->formSnapshotsForAllTrainers($period, $partnerId, $actor);
        $scheme = $this->salaryService->schemeForPeriod($period);
        $canManage = TrainerSalaryAccess::canManageModule($request->user());

        $payload = [
            'message' => 'Сформированы слепки ЗП для ' . $result['snapshots_count'] . ' тренеров',
            'batch_id' => $result['batch_id'],
            'snapshots_count' => $result['snapshots_count'],
            'rows' => $result['rows'],
        ];

        if ($scheme->prefersFullTableReload()) {
            $page = $this->composeDraftPage($partnerId, $year, $month, $canManage);
            $payload['reload_table'] = true;
            $payload['table_html'] = $page['table_html'];
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPagePayload(TrainerSalaryReportRequest $request): array
    {
        $partnerId = $this->requirePartnerId();
        [$year, $month] = $request->resolvedYearMonth();
        $canManage = TrainerSalaryAccess::canManageModule($request->user());

        return $this->composeDraftPage($partnerId, $year, $month, $canManage);
    }

    /**
     * @return array<string, mixed>
     */
    private function composeDraftPage(int $partnerId, int $year, int $month, bool $canManage): array
    {
        $report = $this->salaryService->buildReport($partnerId, $year, $month);
        $scheme = $report['scheme'];
        $draftViewData = $scheme->draftViewData($report['period']);
        $viewData = array_merge([
            'rows' => $report['rows'],
            'canManage' => $canManage,
        ], $draftViewData);

        return [
            'year' => $report['year'],
            'month' => $report['month'],
            'month_label' => $report['month_label'],
            'date_from' => $report['date_from'],
            'date_to' => $report['date_to'],
            'scheme_code' => $scheme->code(),
            'draft_subtitle' => $scheme->draftSubtitle(),
            'draft_view_data' => $draftViewData,
            'table_view' => $scheme->draftTableView(),
            'rows' => $report['rows'],
            'can_manage' => $canManage,
            'show_trainer_types' => $scheme->code() === \App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasTrainerSalaryScheme::CODE,
            'can_manage_trainer_types' => TrainerTypeAccess::canManageCatalog(),
            'canManageTrainerTypes' => TrainerTypeAccess::canManageCatalog(),
            'table_html' => view($scheme->draftTableView(), $viewData)->render(),
        ];
    }
}
