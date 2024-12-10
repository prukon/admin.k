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
    public function __construct()
    {
        $this->middleware('role:admin,superadmin');
    }


//    Страница Пополнить счет
    public function showRecharge()
    {
        return view('payment.paymentPartner', ['activeTab' => 'recharge']);
    }

    //    Страница История платежей
    public function showHistory()
    {
        return view('payment.paymentPartner', ['activeTab' => 'history']);
    }

//    Формирование таблицы для Истории платежей
    public function getPaymentsData(Request $request)
    {
        $query = PartnerPayment::with(['partner', 'user'])
            ->leftJoin('partner_accesses', 'partner_payments.id', '=', 'partner_accesses.partner_payment_id')
            ->select(
                'partner_payments.*',
                'partner_accesses.start_date as access_start_date',
                'partner_accesses.end_date as access_end_date'
            );

        return DataTables::of($query)
            ->addColumn('partner_name', function ($payment) {
                return optional($payment->partner)->title ?? 'N/A';
            })
            ->addColumn('user_name', function ($payment) {
                return optional($payment->user)->name ?? 'N/A';
            })
            ->editColumn('amount', function ($payment) {
                return number_format($payment->amount, 2, ',', ' ') . ' ₽';
            })
            ->editColumn('payment_method', function ($payment) {
                return $payment->payment_method ?? 'N/A';
            })
            ->editColumn('payment_date', function ($payment) {
                return $payment->payment_date
                    ? \Carbon\Carbon::parse($payment->payment_date)->format('d.m.y H:i')
                    : 'N/A';
            })
            ->addColumn('payment_period', function ($payment) {
                if ($payment->access_start_date && $payment->access_end_date) {
                    $startDate = \Carbon\Carbon::parse($payment->access_start_date)->format('d.m.y'); // Формат с двумя цифрами года
                    $endDate = \Carbon\Carbon::parse($payment->access_end_date)->format('d.m.y');
                    return "$startDate - $endDate";
                }
                return 'N/A';
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
                // Получаем дату начала активности
                $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');

                if ($latestEndDate) {
                    $activityStartDate = Carbon::parse($latestEndDate)->addDays(1);
                } else {
                    $activityStartDate = Partner::where('id', $partnerId)->value('activity_start_date');
                }

                // Формируем конечную дату
                if ($activityStartDate) {
                    $activityStartDateParse = Carbon::parse($activityStartDate);
                    $endDate = $activityStartDateParse->addDays(29); // Добавить 30 дней
                } else {
                    throw new \Exception('Не удалось получить дату начала активности партнера.');
                }

                // Создаем запись платежа
                $partnerPayment = PartnerPayment::create([
                    'partner_id' => $partnerId,
                    'user_id' => $userId,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'payment_status' => 'pending',
                    'payment_date' => Carbon::now(),
                    'payment_method' => 'yookassa',
                ]);

                // Создаем доступ с привязкой к записи платежа
                PartnerAccess::create([
                    'partner_payment_id' => $partnerPayment->id, // Используем ID записи платежа
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
