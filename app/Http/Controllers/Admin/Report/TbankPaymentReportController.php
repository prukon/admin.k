<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;
use App\Http\Requests\Admin\Report\TbankPaymentsReportFilterRequest;
use App\Models\Partner;
use App\Models\TinkoffPayment;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class TbankPaymentReportController extends AdminBaseController
{
    public const TABLE_KEY = 'reports_tbank_payments';

    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index(TbankPaymentsReportFilterRequest $request)
    {
        $filters = $request->filters();
        $canFilterPartner = $this->canFilterByPartner();

        $totalQuery = TinkoffPayment::query();
        $this->applyReportFilters($totalQuery, $request);
        $totalRawCents = (int) $totalQuery->sum('amount');
        $totalPaidPrice = number_format($totalRawCents / 100, 0, '', ' ');

        $tpFilterPartner = $canFilterPartner ? $this->resolvePartnerLabel($filters) : null;

        return view('admin.report.index', [
            'activeTab' => 'tbank-payments',
            'filters' => $filters,
            'totalPaidPrice' => $totalPaidPrice,
            'tpFilterPartner' => $tpFilterPartner,
            'tpCanFilterPartner' => $canFilterPartner,
            'tpHasActiveFilters' => $this->hasActiveFilters($filters, $canFilterPartner),
            'tbankPaymentsPageLength' => UserTableSetting::pageLengthForUser(
                Auth::id() !== null ? (int) Auth::id() : null,
                self::TABLE_KEY
            ),
        ]);
    }

    public function total(TbankPaymentsReportFilterRequest $request)
    {
        $totalQuery = TinkoffPayment::query();
        $this->applyReportFilters($totalQuery, $request);
        $rawCents = (int) $totalQuery->sum('amount');
        $raw = $rawCents / 100;

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw' => $raw,
        ]);
    }

    public function data(TbankPaymentsReportFilterRequest $request)
    {
        $query = TinkoffPayment::query()
            ->with('partner')
            ->select('tinkoff_payments.*');

        $this->applyReportFilters($query, $request);

        if (! $request->has('order')) {
            $query->orderByDesc('id');
        }

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                $this->applyDataTableSearch($query, $request);
            })
            ->addColumn('partner_title', function (TinkoffPayment $payment) {
                $title = (string) ($payment->partner->title ?? '');
                if ($title !== '') {
                    return $title;
                }

                return '#'.$payment->partner_id;
            })
            ->addColumn('amount', function (TinkoffPayment $payment) {
                return round(((int) $payment->amount) / 100, 2);
            })
            ->orderColumn('amount', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('tinkoff_payments.amount', $dir);
            })
            ->addColumn('show_url', function (TinkoffPayment $payment) {
                return url('/admin/tinkoff/payments/'.$payment->id);
            })
            ->editColumn('created_at', function (TinkoffPayment $payment) {
                return self::formatReportDateTime($payment->created_at);
            })
            ->toJson();
    }

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
            ->where('table_key', self::TABLE_KEY)
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(ColumnsSettingsWithPageLengthSaveRequest $request)
    {
        $payload = $request->persistPayload();
        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::TABLE_KEY,
            ],
            $payload
        );

        return response()->json(['success' => true]);
    }

    private function canFilterByPartner(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyReportFilters($query, TbankPaymentsReportFilterRequest $request): void
    {
        $filters = $request->filters();

        if ($this->isSuperAdmin()) {
            if ($filters['partner_id'] !== null && $filters['partner_id'] > 0) {
                $query->where('partner_id', $filters['partner_id']);
            }
        } else {
            $partnerId = $this->partnerId();
            if ($partnerId) {
                $query->where('partner_id', (int) $partnerId);
            }
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['created_from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if ($filters['created_to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyDataTableSearch($query, Request $request): void
    {
        $keyword = trim((string) $request->input('search.value', ''));
        if ($keyword === '') {
            return;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';
        $query->where(function ($q) use ($like): void {
            $q->where('tinkoff_payments.order_id', 'like', $like)
                ->orWhere('tinkoff_payments.deal_id', 'like', $like)
                ->orWhereRaw('CAST(tinkoff_payments.id AS CHAR) LIKE ?', [$like])
                ->orWhereHas('partner', function ($p) use ($like): void {
                    $p->where('partners.title', 'like', $like);
                });
        });
    }

    private static function formatReportDateTime(mixed $value): string
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
     * @param  array<string, mixed>  $filters
     */
    private function hasActiveFilters(array $filters, bool $canFilterPartner): bool
    {
        $keys = ['status', 'created_from', 'created_to'];
        if ($canFilterPartner) {
            $keys[] = 'partner_id';
        }
        foreach ($keys as $k) {
            $v = $filters[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 'all') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: int, text: string}|null
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
