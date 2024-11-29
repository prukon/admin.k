<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;


class PartnerPaymentController extends Controller
{
    public function showRecharge()
    {
        // Логика для вкладки "Пополнить счет"
        return view('payment.service', ['activeTab' => 'recharge']);
    }

        public function showHistory()
        {
            return view('payment.service', ['activeTab' => 'history']);
        }
    public function getPaymentsData(Request $request)
    {
        $query = ClientPayment::with(['client', 'user'])->select('client_payments.*');

        return DataTables::of($query)
            ->addColumn('client_name', function ($payment) {
                return $payment->client->title ?? 'N/A';
            })
            ->addColumn('user_name', function ($payment) {
                return $payment->user->name ?? 'N/A';
            })
            ->editColumn('amount', function ($payment) {
                return number_format($payment->amount, 2, ',', ' ') . ' ₽';
            })
            ->editColumn('payment_date', function ($payment) {
                return $payment->payment_date->format('d.m.Y H:i');
            })
            ->editColumn('payment_status', function ($payment) {
                $status = match ($payment->payment_status) {
                'succeeded' => 'Успешно',
                'pending' => 'В ожидании',
                'canceled' => 'Отменён',
                default => 'Неизвестно',
            };

            $statusClass = match ($payment->payment_status) {
            'succeeded' => 'badge-success',
                'pending' => 'badge-warning',
                default => 'badge-danger',
            };

            return '<span class="badge ' . $statusClass . '">' . $status . '</span>';
        })
            ->rawColumns(['payment_status']) // Разрешить HTML
            ->make(true);
    }


    }
