<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PaymentIntentReportController extends Controller
{
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
        $currentPartner = app()->bound('current_partner') ? app('current_partner') : null;
        if ($currentPartner && isset($currentPartner->id)) {
            $q->where('partner_id', (int) $currentPartner->id);
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

        if ($request->filled('partner_id') && ctype_digit((string) $request->query('partner_id'))) {
            $q->where('partner_id', (int) $request->query('partner_id'));
        }

        if ($request->filled('user_id') && ctype_digit((string) $request->query('user_id'))) {
            $q->where('user_id', (int) $request->query('user_id'));
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
            ->editColumn('created_at', function (PaymentIntent $intent) {
                return $intent->created_at ? $intent->created_at->format('Y-m-d H:i:s') : '';
            })
            ->editColumn('paid_at', function (PaymentIntent $intent) {
                return $intent->paid_at ? $intent->paid_at->format('Y-m-d H:i:s') : '';
            })
            ->toJson();
    }
}