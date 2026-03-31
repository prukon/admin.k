<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\AdminBaseController;
use App\Models\PaymentIntent;
use Illuminate\Http\Request;
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
        return view('admin.report.index', [
            'activeTab' => 'payment-intents',
            'filters'   => $request->query(),
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

        // Доп. фильтры из формы
        if ($request->filled('inv_id') && ctype_digit((string) $request->query('inv_id'))) {
            $inv = (int) $request->query('inv_id');
            // inv_id может быть как внутренним intent.id, так и внешним provider_inv_id
            $q->where(function ($sub) use ($inv) {
                $sub->where('id', $inv)->orWhere('provider_inv_id', $inv);
            });
        }

        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }

        if ($request->filled('provider')) {
            $q->where('provider', (string) $request->query('provider'));
        }

        if ($request->filled('partner_title')) {
            $needle = trim((string) $request->query('partner_title'));
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $q->whereHas('partner', function ($sub) use ($like) {
                    $sub->where('partners.title', 'like', $like);
                });
            }
        }

        if ($request->filled('user_name')) {
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
            ->addColumn('payment_method_webhook_label', function (PaymentIntent $intent) {
                return $this->paymentIntentMethodLabel($intent->payment_method_webhook);
            })
            ->editColumn('created_at', function (PaymentIntent $intent) {
                return $intent->created_at ? $intent->created_at->format('Y-m-d H:i:s') : '';
            })
            ->editColumn('paid_at', function (PaymentIntent $intent) {
                return $intent->paid_at ? $intent->paid_at->format('Y-m-d H:i:s') : '';
            })
            ->toJson();
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
}