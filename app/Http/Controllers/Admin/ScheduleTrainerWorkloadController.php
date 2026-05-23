<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\TrainerWorkloadReportRequest;
use App\Services\PartnerContext;
use App\Services\Schedule\TrainerWorkloadReportService;
use Illuminate\Http\JsonResponse;

class ScheduleTrainerWorkloadController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TrainerWorkloadReportService $reportService,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(TrainerWorkloadReportRequest $request)
    {
        $payload = $this->buildReportPayload($request);

        return view('admin.schedule.index', [
            'activeTab' => 'trainer-workload',
            'dateFrom' => $payload['date_from'],
            'dateTo' => $payload['date_to'],
            'showGroups' => $payload['show_groups'],
            'report' => $payload['report'],
        ]);
    }

    public function data(TrainerWorkloadReportRequest $request): JsonResponse
    {
        return response()->json($this->buildReportPayload($request));
    }

    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     show_groups: bool,
     *     report: array<string, mixed>,
     *     table_html: string
     * }
     */
    private function buildReportPayload(TrainerWorkloadReportRequest $request): array
    {
        $partnerId = $this->requirePartnerId();
        [$dateFrom, $dateTo] = $request->resolvedPeriodStrings();
        $showGroups = $request->showGroups();

        $report = $this->reportService->build($partnerId, $dateFrom, $dateTo, $showGroups);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'show_groups' => $showGroups,
            'report' => $report,
            'table_html' => view('admin.schedule._trainer_workload_table', [
                'weekdays' => $report['weekdays'],
                'trainers' => $report['trainers'],
                'cells' => $report['cells'],
                'rowTotals' => $report['row_totals'],
                'columnTotals' => $report['column_totals'],
                'grandTotal' => $report['grand_total'],
                'showGroups' => $showGroups,
            ])->render(),
        ];
    }
}
