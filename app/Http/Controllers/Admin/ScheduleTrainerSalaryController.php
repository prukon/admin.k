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
            'canManageTrainerSalary' => $request->user()?->can('schedule.trainerSalary.manage') ?? false,
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

        return response()->json([
            'message' => 'Черновик сохранён',
            'row' => $row,
        ]);
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

        return response()->json([
            'message' => 'Слепок ЗП сформирован (версия ' . ($result['snapshot']['version'] ?? '') . ')',
            'snapshot' => $result['snapshot'],
            'row' => $result['row'],
        ]);
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

        return response()->json([
            'message' => 'Сформированы слепки ЗП для ' . $result['snapshots_count'] . ' тренеров',
            'batch_id' => $result['batch_id'],
            'snapshots_count' => $result['snapshots_count'],
            'rows' => $result['rows'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPagePayload(TrainerSalaryReportRequest $request): array
    {
        $partnerId = $this->requirePartnerId();
        [$year, $month] = $request->resolvedYearMonth();

        $report = $this->salaryService->buildReport($partnerId, $year, $month);

        return [
            'year' => $report['year'],
            'month' => $report['month'],
            'month_label' => $report['month_label'],
            'date_from' => $report['date_from'],
            'date_to' => $report['date_to'],
            'rows' => $report['rows'],
            'can_manage' => $request->user()?->can('schedule.trainerSalary.manage') ?? false,
            'table_html' => view('admin.schedule._trainer_salary_table', [
                'rows' => $report['rows'],
                'canManage' => $request->user()?->can('schedule.trainerSalary.manage') ?? false,
            ])->render(),
        ];
    }
}
