<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\Report\OutgoingEmailReportSelect2SearchRequest;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;
use App\Mail\PaymentNotificationMail;
use App\Models\OutgoingEmailLog;
use App\Models\Partner;
use App\Models\UserTableSetting;
use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

/**
 * Отчёт «Исходящие письма» — /admin/reports/emails
 *
 * Источник данных: таблица outgoing_email_logs (наполняется
 * App\Listeners\LogOutgoingEmail на событиях MessageSending/MessageSent).
 *
 * Scope: non-superadmin — только свой partner_id; superadmin — все партнёры
 * + опциональный фильтр partner_id (паритет с чеками / payment intents).
 */
class OutgoingEmailReportController extends AdminBaseController
{
    private const TABLE_KEY = 'reports_outgoing_emails';

    /** Доступные значения статуса (см. OutgoingEmailLog). */
    private const ALLOWED_STATUSES = [
        OutgoingEmailLog::STATUS_SENDING,
        OutgoingEmailLog::STATUS_SENT,
        OutgoingEmailLog::STATUS_FAILED,
    ];

    /** Максимум символов превью ошибки в таблице (плана задачи). */
    private const ERROR_EXCERPT_MAX = 200;

    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index(Request $request)
    {
        $canFilterPartner = $this->canFilterByPartner();
        $totals = $this->computeTotals($request);
        $filters = $request->query();

        $emailsFilterMailable = $this->resolveMailableFilterLabel($request, $filters);
        $emailsFilterPartner = $canFilterPartner ? $this->resolvePartnerLabel($filters) : null;

        return view('admin.report.index', [
            'activeTab' => 'emails',
            'filters' => $filters,
            'emailsToolbar' => $totals,
            'emailsFilterMailable' => $emailsFilterMailable,
            'emailsFilterPartner' => $emailsFilterPartner,
            'emailsCanFilterPartner' => $canFilterPartner,
            'emailsHasActiveFilters' => $this->hasActiveFilters($filters, $canFilterPartner),
        ]);
    }

    /**
     * Тулбар: счётчики «Всего / Отправлено / Ошибки» по тем же фильтрам, что и таблица.
     */
    public function total(Request $request)
    {
        $totals = $this->computeTotals($request);

        return response()->json($totals);
    }

    /**
     * Server-side данные для DataTables.
     */
    public function data(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        // Лёгкая выборка: тяжёлые поля (html_body/text_body/attachments) не тащим.
        $query = OutgoingEmailLog::query()
            ->with(['partner:id,title'])
            ->select([
                'id',
                'partner_id',
                'created_at',
                'sent_at',
                'failed_at',
                'status',
                'from_address',
                'from_name',
                'to_summary',
                'subject',
                'mailable_class',
                'notification_class',
                'send_attempts',
                'error_message',
            ]);

        $this->applyPartnerScopeToQuery($query, $request);
        $this->applyFilters($query, $request);

        $hasOrder = is_array($request->input('order')) && count($request->input('order')) > 0;
        if (! $hasOrder) {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('partner_title', function (OutgoingEmailLog $row) {
                return (string) ($row->partner->title ?? '');
            })
            ->addColumn('mailable_short', function (OutgoingEmailLog $row) {
                return $this->shortClassName(
                    $row->mailable_class !== null && $row->mailable_class !== ''
                        ? (string) $row->mailable_class
                        : (string) ($row->notification_class ?? '')
                );
            })
            ->addColumn('error_excerpt', function (OutgoingEmailLog $row) {
                $err = (string) ($row->error_message ?? '');
                if ($err === '') {
                    return '';
                }
                return Str::limit($err, self::ERROR_EXCERPT_MAX, '…');
            })
            ->addColumn('show_url', function (OutgoingEmailLog $row) {
                return route('reports.emails.show', ['log' => $row->id]);
            })
            ->editColumn('created_at', function (OutgoingEmailLog $row) {
                return optional($row->created_at)->format('Y-m-d H:i:s');
            })
            ->editColumn('sent_at', function (OutgoingEmailLog $row) {
                return optional($row->sent_at)->format('Y-m-d H:i:s');
            })
            ->orderColumn('created_at', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('outgoing_email_logs.created_at', $dir);
            })
            ->orderColumn('sent_at', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('outgoing_email_logs.sent_at', $dir);
            })
            ->orderColumn('mailable_short', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw("COALESCE(mailable_class, notification_class) {$dir}");
            })
            ->orderColumn('partner_id', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderBy('outgoing_email_logs.partner_id', $dir);
            })
            ->toJson();
    }

    /**
     * Select2: поиск партнёров (по названию).
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

    /**
     * Select2: уникальные классы Mailable/Notification у логов (в рамках scope).
     */
    public function mailableClassesSearch(OutgoingEmailReportSelect2SearchRequest $request)
    {
        $q = (string) ($request->validated()['q'] ?? '');

        $query = OutgoingEmailLog::query()
            ->select([
                DB::raw('COALESCE(mailable_class, notification_class) as class_name'),
            ])
            ->whereNotNull(DB::raw('COALESCE(mailable_class, notification_class)'));

        $this->applyPartnerScopeToQuery($query, $request);

        $query->when($q !== '', function ($qq) use ($q) {
            $needle = '%'.$q.'%';
            $qq->where(function ($w) use ($needle) {
                $w->where('mailable_class', 'like', $needle)
                    ->orWhere('notification_class', 'like', $needle);
            });
        })
            ->groupBy('class_name')
            ->orderBy('class_name')
            ->limit(50);

        $rows = $query
            ->pluck('class_name')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        $results = $rows->map(function (string $cls) {
            return [
                'id'   => $cls,
                'text' => $this->shortClassName($cls).' ('.$cls.')',
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

    public function saveColumnsSettings(Request $request)
    {
        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $normalized = [];
        foreach ((array) $data['columns'] as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[(string) $key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Show-страница (отдельная) или HTML-фрагмент для модалки (AJAX / modal=1).
     */
    public function show(Request $request, OutgoingEmailLog $log)
    {
        if (! $this->isSuperAdmin()) {
            $partnerId = $this->requirePartnerId();
            if ((int) ($log->partner_id ?? 0) !== $partnerId) {
                abort(403);
            }
        }

        $inModal = $request->ajax() || $request->boolean('modal');

        if ($inModal) {
            return view('admin.report.partials.outgoing_email_show_content', [
                'log'     => $log,
                'inModal' => true,
            ]);
        }

        return view('admin.report.outgoing_email_show', [
            'log' => $log,
        ]);
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    /**
     * Фильтр «Партнер» — только superadmin (видит кросс-партнёрные данные).
     */
    private function canFilterByPartner(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * @param  Builder<OutgoingEmailLog>  $query
     */
    private function applyPartnerScopeToQuery($query, Request $request): void
    {
        if ($this->isSuperAdmin()) {
            if ($request->filled('partner_id') && ctype_digit((string) $request->input('partner_id'))) {
                $pid = (int) $request->input('partner_id');
                if ($pid > 0) {
                    $query->where('outgoing_email_logs.partner_id', $pid);
                }
            }

            return;
        }

        $partnerId = $this->requirePartnerId();
        $query->where('outgoing_email_logs.partner_id', $partnerId);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasActiveFilters(array $filters, bool $canFilterPartner): bool
    {
        $keys = ['created_at_from', 'created_at_to', 'sent_at_from', 'sent_at_to', 'status', 'mailable_class', 'email_category', 'q'];
        if ($canFilterPartner) {
            $keys[] = 'partner_id';
        }
        foreach ($keys as $k) {
            $v = $filters[$k] ?? null;
            if (is_array($v)) {
                if ($v !== []) {
                    return true;
                }
                continue;
            }
            if ($v !== null && $v !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{
     *     total_raw: int,
     *     total_formatted: string,
     *     sent_raw: int,
     *     sent_formatted: string,
     *     failed_raw: int,
     *     failed_formatted: string
     * }
     */
    private function computeTotals(Request $request): array
    {
        $base = OutgoingEmailLog::query();
        $this->applyPartnerScopeToQuery($base, $request);
        $this->applyFilters($base, $request);

        $totalRaw  = (int) (clone $base)->count();
        $sentRaw   = (int) (clone $base)->where('status', OutgoingEmailLog::STATUS_SENT)->count();
        $failedRaw = (int) (clone $base)->where('status', OutgoingEmailLog::STATUS_FAILED)->count();

        return [
            'total_raw'        => $totalRaw,
            'total_formatted'  => number_format($totalRaw, 0, '', ' '),
            'sent_raw'         => $sentRaw,
            'sent_formatted'   => number_format($sentRaw, 0, '', ' '),
            'failed_raw'       => $failedRaw,
            'failed_formatted' => number_format($failedRaw, 0, '', ' '),
        ];
    }

    /**
     * @param  Builder<OutgoingEmailLog>  $query
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('created_at_from')) {
            $query->whereDate('outgoing_email_logs.created_at', '>=', (string) $request->query('created_at_from'));
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('outgoing_email_logs.created_at', '<=', (string) $request->query('created_at_to'));
        }
        if ($request->filled('sent_at_from')) {
            $query->whereDate('outgoing_email_logs.sent_at', '>=', (string) $request->query('sent_at_from'));
        }
        if ($request->filled('sent_at_to')) {
            $query->whereDate('outgoing_email_logs.sent_at', '<=', (string) $request->query('sent_at_to'));
        }

        if ($request->filled('status')) {
            $raw = $request->query('status');
            $values = is_array($raw) ? $raw : [$raw];
            $values = array_values(array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', $values), static fn ($v) => $v !== ''));
            $values = array_values(array_intersect($values, self::ALLOWED_STATUSES));
            if ($values !== []) {
                $query->whereIn('outgoing_email_logs.status', $values);
            }
        }

        if ($request->filled('mailable_class')) {
            $cls = (string) $request->query('mailable_class');
            if ($cls !== '') {
                $query->where(function ($w) use ($cls) {
                    $w->where('outgoing_email_logs.mailable_class', $cls)
                        ->orWhere('outgoing_email_logs.notification_class', $cls);
                });
            }
        }

        if ($request->filled('email_category')) {
            $category = trim((string) $request->query('email_category'));
            if ($category === PaymentNotificationMail::CATEGORY) {
                $query->where('outgoing_email_logs.mailable_class', PaymentNotificationMail::class);
            }
        }

        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($w) use ($needle) {
                $w->where('outgoing_email_logs.subject', 'like', $needle)
                    ->orWhere('outgoing_email_logs.to_summary', 'like', $needle)
                    ->orWhere('outgoing_email_logs.from_address', 'like', $needle)
                    ->orWhere('outgoing_email_logs.error_message', 'like', $needle);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: string, text: string}|null
     */
    private function resolveMailableFilterLabel(Request $request, array $filters): ?array
    {
        $cls = $filters['mailable_class'] ?? null;
        if (! is_string($cls) || $cls === '') {
            return null;
        }

        $existsQuery = OutgoingEmailLog::query()
            ->where(function ($w) use ($cls) {
                $w->where('mailable_class', $cls)
                    ->orWhere('notification_class', $cls);
            });
        $this->applyPartnerScopeToQuery($existsQuery, $request);

        if (! $existsQuery->limit(1)->exists()) {
            return null;
        }

        return [
            'id' => $cls,
            'text' => $this->shortClassName($cls).' ('.$cls.')',
        ];
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

    private function shortClassName(?string $fqcn): string
    {
        $fqcn = (string) $fqcn;
        if ($fqcn === '') {
            return '';
        }
        if ($fqcn === PaymentNotificationMail::class) {
            return PaymentNotificationMail::CATEGORY_LABEL;
        }
        $parts = explode('\\', $fqcn);
        $last = end($parts);
        return is_string($last) && $last !== '' ? $last : $fqcn;
    }
}
