<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use YooKassa\Client;


class PartnerPaymentController extends Controller
{

//    Страница Пополнить счет
    public function showRecharge()
    {
        return view('payment.service', ['activeTab' => 'recharge']);
    }

    //    Страница История платежей
    public function showHistory()
    {
        return view('payment.service', ['activeTab' => 'history']);
    }

//    Формирование таблицы для Истории платежей
    public function getPaymentsData(Request $request)
    {
        $query = PartnerPayment::with(['partner', 'user'])->select('partner_payments.*');

        return DataTables::of($query)
            ->addColumn('partner_name', function ($payment) {
                return $payment->partner->title ?? 'N/A';
            })
            ->addColumn('user_name', function ($payment) {
                return $payment->user->name ?? 'N/A';
            })
            ->editColumn('amount', function ($payment) {
                return number_format($payment->amount, 2, ',', ' ') . ' ₽';
            })
            ->editColumn('payment_method', function ($payment) {
                return $payment->payment_method ?? 'N/A';
            })
            ->editColumn('payment_date', function ($payment) {
                return $payment->payment_date->format('d.m.Y H:i');
            })
            ->editColumn('payment_status', function ($payment) {
                $status = match($payment->payment_status){
                'succeeded' => 'Успешно',
                'pending' => 'В ожидании',
                'canceled' => 'Отменён',
                default => 'Неизвестно',
            };

            $statusClass = match($payment->payment_status){
            'succeeded' => 'badge-success',
                'pending' => 'badge-warning',
                default => 'badge-danger',
            };

            return '<span class="badge ' . $statusClass . '">' . $status . '</span>';
        })
            ->rawColumns(['payment_status'])// Разрешить HTML
            ->make(true);
    }

    //    Формирование платежа Yookassa
    public function createPaymentYookassa(Request $request)
    {
        // Валидация входных данных
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'partner_id' => 'required|exists:partners,id',
        ]);

        $client = new Client();
        $client->setAuth(config('yookassa.shop_id'), config('yookassa.secret_key'));

        $amount = $request->input('amount');
        $partnerId = $request->input('partner_id');
        $userId = auth()->id();

        if (!$userId) {
            return back()->withErrors(['message' => 'Пользователь не аутентифицирован.']);
        }

        try {
            $payment = $client->createPayment([
                'amount' => [
                    'value' => $amount,
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => config('yookassa.success_url'),
//                    'return_url' => $returnUrl,
                ],
                'capture' => true,
                'description' => 'Оплата заказа №123',
                'receipt' => [
                    'customer' => [
                        'email' => 'test@example.com',
                    ],
                    'items' => [
                        [
                            'description' => 'Тестовый товар',
                            'quantity' => 1,
                            'amount' => [
                                'value' => $amount,
                                'currency' => 'RUB',
                            ],
                            'vat_code' => 1,
                            'payment_mode' => 'full_prepayment',
                            'payment_subject' => 'commodity',
                        ],
                    ],
                ],
            ], uniqid('', true));

            $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();

            if (!$confirmationUrl) {
                return back()->withErrors(['message' => 'Не удалось получить URL подтверждения платежа.']);
            }

            // Используем транзакцию
            \DB::transaction(function () use ($payment, $partnerId, $userId, $amount) {


//                Начало оплаченного периода берем от последнего оплаченного периода
                $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');

                if ($latestEndDate) {
                    $activityStartDate = Carbon::parse($latestEndDate)->addDays(1);

                } else {
                    $activityStartDate = Partner::where('id', $partnerId)->value('activity_start_date');
                }


//                Формируем конечную дату
                if ($activityStartDate) {
                    $activityStartDateParse = Carbon::parse($activityStartDate);
                    $endDate = $activityStartDateParse->addDays(29); // Добавить 30 дней
                } else {
                    throw new \Exception('Не удалось получить дату начала активности партнера.');
                }

                PartnerPayment::create([
                    'partner_id' => $partnerId,
                    'user_id' => $userId,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'payment_status' => 'pending',
                    'payment_date' => Carbon::now(),
                    'payment_method' => 'yookassa',
                ]);

                PartnerAccess::create([
                    'payment_id' => $payment->id,
                    'start_date' => $activityStartDate,
                    'end_date' => $endDate,
                    'is_active' => 0,
                ]);


            });

            // Перенаправляем пользователя на страницу подтверждения
            return redirect($confirmationUrl);

        } catch (\Exception $e) {
            \Log::error('Ошибка при создании платежа или записи в базу: ' . $e->getMessage());


            // Попытка отменить платеж через Yookassa, если это возможно
            try {
                if (isset($payment) && $payment->id) {
                    $client->cancelPayment($payment->id);
                }
            } catch (\Exception $cancelException) {
                \Log::error('Ошибка при отмене платежа: ' . $cancelException->getMessage());
            }


            return back()->withErrors(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }


}
