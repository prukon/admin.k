<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\TrainerSalarySheetsReportRequest;
use App\Models\TrainerSalarySnapshot;
use App\Services\PartnerContext;
use App\Services\Schedule\TrainerSalarySheetService;
use Illuminate\Http\JsonResponse;

class ScheduleTrainerSalarySheetsController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TrainerSalarySheetService $sheetService,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(TrainerSalarySheetsReportRequest $request)
    {
        $payload = $this->buildListPayload($request);

        return view('admin.schedule.index', array_merge($payload, [
            'activeTab' => 'trainer-salary-sheets',
        ]));
    }

    public function data(TrainerSalarySheetsReportRequest $request): JsonResponse
    {
        return response()->json($this->buildListPayload($request));
    }

    public function showBatch(TrainerSalarySheetsReportRequest $request, string $batchId)
    {
        $partnerId = $this->requirePartnerId();
        $sheet = $this->sheetService->showBatch($partnerId, $batchId);

        return view('admin.schedule.trainer_salary_sheet_show', [
            'activeTab' => 'trainer-salary-sheets',
            'sheet' => $sheet,
            'listUrl' => route('schedule.trainer-salary-sheets', [
                'year' => $sheet['year'],
                'month' => $sheet['month'],
            ]),
        ]);
    }

    public function showSnapshot(TrainerSalarySheetsReportRequest $request, TrainerSalarySnapshot $snapshot)
    {
        $partnerId = $this->requirePartnerId();
        $sheet = $this->sheetService->showSnapshot($partnerId, $snapshot);

        return view('admin.schedule.trainer_salary_sheet_show', [
            'activeTab' => 'trainer-salary-sheets',
            'sheet' => $sheet,
            'listUrl' => route('schedule.trainer-salary-sheets', [
                'year' => $sheet['year'],
                'month' => $sheet['month'],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListPayload(TrainerSalarySheetsReportRequest $request): array
    {
        $partnerId = $this->requirePartnerId();
        [$year, $month] = $request->resolvedYearMonth();

        $report = $this->sheetService->listSheets(
            $partnerId,
            $year,
            $month,
            $request->latestOnly(),
        );

        return [
            'year' => $report['year'],
            'month' => $report['month'],
            'month_label' => $report['month_label'],
            'latest_only' => $request->latestOnly(),
            'sheets' => $report['sheets'],
            'latest_by_trainer' => $report['latest_by_trainer'],
            'table_html' => view('admin.schedule._trainer_salary_sheets_list', [
                'sheets' => $report['sheets'],
            ])->render(),
            'latest_html' => view('admin.schedule._trainer_salary_sheets_latest', [
                'latestByTrainer' => $report['latest_by_trainer'],
            ])->render(),
        ];
    }
}
