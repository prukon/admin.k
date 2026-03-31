<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;
use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class FiscalReceiptReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        $filters = $request->query();

        $totalQuery = FiscalReceipt::query();
        $this->applyFiscalReceiptReportFilters($totalQuery, $request);
        $totalRaw = (float) $totalQuery->sum('amount');
        $totalPaidPrice = number_format($totalRaw, 0, '', ' ');

        $frFilterPartner = $this->resolvePartnerLabel($filters);

        return view('admin.report.index', [
            'activeTab' => 'fiscal-receipts',
            'filters' => $filters,
            'totalPaidPrice' => $totalPaidPrice,
            'frFilterPartner' => $frFilterPartner,
        ]);
    }

    /**
     * Сумма amount по тем же фильтрам, что и таблица (шапка без перезагрузки).
     */
    public function total(Request $request)
    {
        $totalQuery = FiscalReceipt::query();
        $this->applyFiscalReceiptReportFilters($totalQuery, $request);
        $raw = (float) $totalQuery->sum('amount');

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw' => $raw,
        ]);
    }

    public function data(Request $request)
    {
        $query = FiscalReceipt::query()
            ->with(['partner', 'paymentIntent'])
            ->select('fiscal_receipts.*');

        $this->applyFiscalReceiptReportFilters($query, $request);

        if (! $request->has('order')) {
            $query->orderByDesc('id');
        }

        return DataTables::of($query)
            ->addColumn('partner_title', function (FiscalReceipt $receipt) {
                return (string) ($receipt->partner->title ?? ($receipt->partner->name ?? ''));
            })
            ->editColumn('created_at', function (FiscalReceipt $receipt) {
                return $this->formatDateTime($receipt->created_at);
            })
            ->editColumn('queued_at', function (FiscalReceipt $receipt) {
                return $this->formatDateTime($receipt->queued_at);
            })
            ->editColumn('processed_at', function (FiscalReceipt $receipt) {
                return $this->formatDateTime($receipt->processed_at);
            })
            ->editColumn('failed_at', function (FiscalReceipt $receipt) {
                return $this->formatDateTime($receipt->failed_at);
            })
            ->toJson();
    }

    /**
     * Select2: поиск партнеров (по названию).
     */
    public function partnersSearch(PaymentsReportSelect2SearchRequest $request)
    {
        $q = (string) ($request->validated()['q'] ?? '');

        $partners = Partner::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('title', 'like', '%'.$q.'%');
            })
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title']);

        $results = $partners->map(static function (Partner $p) {
            return [
                'id' => $p->id,
                'text' => (string) ($p->title ?? ''),
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function getColumnsSettings()
    {
        $settings = UserTableSetting::query()
            ->where('user_id', (int) Auth::id())
            ->where('table_key', 'reports_fiscal_receipts')
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(Request $request)
    {
        $validated = $request->validate([
            'columns' => 'required|array',
        ]);

        $normalized = [];
        foreach ($validated['columns'] as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $normalized[$key] = $bool === true;
        }

        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => 'reports_fiscal_receipts',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyFiscalReceiptReportFilters($query, Request $request): void
    {
        $partnerId = $this->partnerId();
        if ($partnerId) {
            $query->where('partner_id', (int) $partnerId);
        }

        if ($request->filled('id') && ctype_digit((string) $request->input('id'))) {
            $query->where('id', (int) $request->input('id'));
        }

        if ($request->filled('payment_intent_id') && ctype_digit((string) $request->input('payment_intent_id'))) {
            $query->where('payment_intent_id', (int) $request->input('payment_intent_id'));
        }

        if ($request->filled('payment_id') && ctype_digit((string) $request->input('payment_id'))) {
            $query->where('payment_id', (int) $request->input('payment_id'));
        }

        if ($request->filled('partner_id') && ctype_digit((string) $request->input('partner_id'))) {
            $query->where('partner_id', (int) $request->input('partner_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('external_id')) {
            $query->where('external_id', trim((string) $request->input('external_id')));
        }

        $this->applyDateRangeFilter($query, $request, 'created_at', 'created_from', 'created_to');
        $this->applyDateRangeFilter($query, $request, 'processed_at', 'processed_from', 'processed_to');
        $this->applyDateRangeFilter($query, $request, 'failed_at', 'failed_from', 'failed_to');
    }

    private function applyDateRangeFilter($query, Request $request, string $column, string $fromField, string $toField): void
    {
        if ($request->filled($fromField)) {
            $query->whereDate($column, '>=', (string) $request->input($fromField));
        }

        if ($request->filled($toField)) {
            $query->whereDate($column, '<=', (string) $request->input($toField));
        }
    }

    private function formatDateTime($value): string
    {
        if (! $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolvePartnerLabel(array $filters): ?array
    {
        $raw = $filters['partner_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $pid = (int) $raw;
        if ($pid <= 0) {
            return null;
        }

        $p = Partner::query()->where('id', $pid)->first(['id', 'title']);
        if (! $p) {
            return null;
        }

        return [
            'id' => $p->id,
            'text' => (string) ($p->title ?? ''),
        ];
    }
}
