<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\Report\PaymentIntentsUsersSelect2SearchRequest;
use App\Http\Requests\Admin\Report\PaymentsReportSelect2SearchRequest;
use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Services\PartnerContext;

class PaymentIntentReportController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    // Отчёт -> "Платежные запросы" (payment_intents)
    public function paymentIntents(Request $request)
    {
        $totalQuery = PaymentIntent::query();

        $partnerId = $this->partnerId();
        if ($partnerId) {
            $totalQuery->where('partner_id', (int) $partnerId);
        }

        $this->applyFilters($totalQuery, $request);

        $totalRaw = (float) $totalQuery->sum('out_sum');
        $totalPaidPrice = number_format($totalRaw, 0, '', ' ');

        $filters = $request->query();
        $piFilterPartner = $this->resolvePartnerLabel($filters);
        $piFilterUser = $this->resolveUserLabel($filters);

        return view('admin.report.index', [
            'activeTab' => 'payment-intents',
            'filters'   => $filters,
            'totalPaidPrice' => $totalPaidPrice,
            'piFilterPartner' => $piFilterPartner,
            'piFilterUser' => $piFilterUser,
        ]);
    }

    /**
     * Сумма платежных запросов по тем же фильтрам, что и таблица (шапка без перезагрузки страницы).
     */
    public function total(Request $request)
    {
        $totalQuery = PaymentIntent::query();

        $partnerId = $this->partnerId();
        if ($partnerId) {
            $totalQuery->where('partner_id', (int) $partnerId);
        }

        $this->applyFilters($totalQuery, $request);

        $raw = (float) $totalQuery->sum('out_sum');

        return response()->json([
            'total_formatted' => number_format($raw, 0, '', ' '),
            'total_raw'       => $raw,
        ]);
    }

    // Данные для отчёта "Платежные запросы" (DataTables, server-side)
    public function getPaymentIntents(Request $request)
    {
        $q = PaymentIntent::query()
            ->with(['user', 'partner'])
            ->select('payment_intents.*');

        // По умолчанию ограничиваем текущим партнёром (если контекст выбран)
        $partnerId = $this->partnerId();
        if ($partnerId) {
            $q->where('partner_id', (int) $partnerId);
        }

        $this->applyFilters($q, $request);

        // Важно: базовая сортировка по id desc (если DataTables не прислал order)
        if (! $request->has('order')) {
            $q->orderByDesc('id');
        }

        return DataTables::of($q)
            ->addColumn('partner_title', function (PaymentIntent $intent) {
                return (string) ($intent->partner->title ?? ($intent->partner->name ?? ''));
            })
            ->addColumn('user_name', function (PaymentIntent $intent) {
                return (string) ($intent->user->full_name ?? ($intent->user->name ?? ''));
            })
            ->addColumn('client_os', function (PaymentIntent $intent) {
                return self::joinClientContextParts(
                    $intent->client_os_family,
                    $intent->client_os_version
                );
            })
            ->addColumn('client_browser', function (PaymentIntent $intent) {
                return self::joinClientContextParts(
                    $intent->client_browser_family,
                    $intent->client_browser_version
                );
            })
            ->addColumn('payment_method_webhook_label', function (PaymentIntent $intent) {
                return $this->paymentIntentMethodLabel($intent->payment_method_webhook);
            })
            ->editColumn('created_at', function (PaymentIntent $intent) {
                return $intent->created_at ? $intent->created_at->format('Y-m-d H:i:s') : '';
            })
            ->editColumn('paid_at', function (PaymentIntent $intent) {
                return $intent->paid_at ? $intent->paid_at->format('Y-m-d H:i:s') : '';
            })
            ->editColumn('payment_date', function (PaymentIntent $intent) {
                return self::formatPaymentDateForReport($intent->payment_date);
            })
            ->rawColumns([
                'partner_title',
                'user_name',
                'client_os',
                'client_browser',
                'payment_method_webhook_label',
                'payment_date',
            ])
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

    /**
     * Select2: поиск пользователей (опционально в рамках партнера).
     */
    public function usersSearch(PaymentIntentsUsersSelect2SearchRequest $request)
    {
        $v = $request->validated();
        $q = (string) ($v['q'] ?? '');
        $partnerId = (int) ($v['partner_id'] ?? 0);

        $fallbackPartnerId = (int) ($this->partnerId() ?? 0);
        if ($partnerId <= 0 && $fallbackPartnerId > 0) {
            $partnerId = $fallbackPartnerId;
        }

        $users = User::query()
            ->when($partnerId > 0, fn ($qq) => $qq->where('partner_id', $partnerId))
            ->when($q !== '', function ($qq) use ($q) {
                $needle = '%'.$q.'%';
                $qq->where(function ($w) use ($needle) {
                    $w->where('name', 'like', $needle)
                        ->orWhere('lastname', 'like', $needle);
                });
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'lastname', 'partner_id']);

        $results = $users->map(function (User $u) {
            $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));
            return [
                'id' => $u->id,
                'text' => $text !== '' ? $text : '—',
                'partner_id' => $u->partner_id,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "reports_payment_intents".
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'reports_payment_intents')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя.
     * Ожидает: columns: { provider_inv_id: true, partner: false, ... }
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => 'reports_payment_intents',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    private function paymentIntentMethodLabel(?string $code): string
    {
        $code = $code !== null ? trim($code) : '';
        if ($code === '') {
            return '';
        }

        return match ($code) {
            'card' => 'Карта',
            'sbp_qr' => 'QR (СБП)',
            'tpay' => 'T‑Pay',
            default => $code,
        };
    }

    private function applyFilters($q, Request $request): void
    {
        if ($request->filled('inv_id') && ctype_digit((string) $request->query('inv_id'))) {
            $inv = (int) $request->query('inv_id');
            $q->where(function ($sub) use ($inv) {
                $sub->where('id', $inv)->orWhere('provider_inv_id', $inv);
            });
        }

        if ($request->filled('partner_id') && ctype_digit((string) $request->query('partner_id'))) {
            $pid = (int) $request->query('partner_id');
            if ($pid > 0) {
                $q->where('partner_id', $pid);
            }
        } elseif ($request->filled('partner_title')) {
            $needle = trim((string) $request->query('partner_title'));
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $q->whereHas('partner', function ($sub) use ($like) {
                    $sub->where('partners.title', 'like', $like);
                });
            }
        }

        if ($request->filled('user_id') && ctype_digit((string) $request->query('user_id'))) {
            $uid = (int) $request->query('user_id');
            if ($uid > 0) {
                $q->where('user_id', $uid);
            }
        } elseif ($request->filled('user_name')) {
            $needle = trim((string) $request->query('user_name'));
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $q->whereHas('user', function ($sub) use ($like) {
                    $sub->where(function ($w) use ($like) {
                        $w->where('users.name', 'like', $like)
                            ->orWhere('users.lastname', 'like', $like);
                    });
                });
            }
        }

        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }

        if ($request->filled('provider')) {
            $q->where('provider', (string) $request->query('provider'));
        }

        if ($request->filled('created_from')) {
            $q->whereDate('created_at', '>=', (string) $request->query('created_from'));
        }
        if ($request->filled('created_to')) {
            $q->whereDate('created_at', '<=', (string) $request->query('created_to'));
        }

        if ($request->filled('paid_from')) {
            $q->whereDate('paid_at', '>=', (string) $request->query('paid_from'));
        }
        if ($request->filled('paid_to')) {
            $q->whereDate('paid_at', '<=', (string) $request->query('paid_to'));
        }
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

    /**
     * @param array<string, mixed> $filters
     */
    private function resolveUserLabel(array $filters): ?array
    {
        $raw = $filters['user_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $uid = (int) $raw;
        if ($uid <= 0) {
            return null;
        }

        $u = User::query()->where('id', $uid)->first(['id', 'name', 'lastname', 'partner_id']);
        if (! $u) {
            return null;
        }

        $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

        return [
            'id' => $u->id,
            'text' => $text !== '' ? $text : '—',
        ];
    }

    private static function joinClientContextParts(?string $family, ?string $version): string
    {
        $a = trim((string) ($family ?? ''));
        $b = trim((string) ($version ?? ''));

        if ($a !== '' && $b !== '') {
            return $a.' '.$b;
        }

        return $a !== '' ? $a : $b;
    }

    /**
     * Человекочитаемый «оплаченный месяц» для отчёта: YYYY-MM-DD → «март 2026».
     * Произвольные строки (например «Клубный взнос») возвращаются как есть.
     */
    private static function formatPaymentDateForReport(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}:\d{2})?$/', $raw, $matches)) {
            return $raw;
        }

        $month = (int) $matches[2];
        $year = (int) $matches[1];

        if ($month < 1 || $month > 12) {
            return $raw;
        }

        static $monthNames = [
            1 => 'январь',
            2 => 'февраль',
            3 => 'март',
            4 => 'апрель',
            5 => 'май',
            6 => 'июнь',
            7 => 'июль',
            8 => 'август',
            9 => 'сентябрь',
            10 => 'октябрь',
            11 => 'ноябрь',
            12 => 'декабрь',
        ];

        return $monthNames[$month].' '.$year;
    }
}