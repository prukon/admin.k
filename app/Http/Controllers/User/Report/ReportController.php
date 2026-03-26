<?php

namespace App\Http\Controllers\User\Report;

use App\Http\Controllers\Controller;
use App\Models\FiscalReceipt;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
//use function Termwind\dd;
//use function Termwind\dd;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $curUser; // Определяем свойство для хранения текущего пользователя

    public function formatedDate($month)
    {
        // Массив соответствий русских и английских названий месяцев
        $months = [
            'Январь' => 'January',
            'Февраль' => 'February',
            'Март' => 'March',
            'Апрель' => 'April',
            'Май' => 'May',
            'Июнь' => 'June',
            'Июль' => 'July',
            'Август' => 'August',
            'Сентябрь' => 'September',
            'Октябрь' => 'October',
            'Ноябрь' => 'November',
            'Декабрь' => 'December',
        ];

        // Разделение строки на месяц и год
        $parts = explode(' ', $month);
        if (count($parts) === 2 && isset($months[$parts[0]])) {
            $month = $months[$parts[0]] . ' ' . $parts[1]; // Замена русского месяца на английский
        } else {
            return null; // Если формат не соответствует "Месяц Год", возвращаем null
        }

        // Преобразуем строку в объект DateTime
        try {
            $date = \DateTime::createFromFormat('F Y', $month); // F - имя месяца, Y - год
            if ($date) {
                return $date->format('Y-m-01'); // Всегда возвращаем первое число месяца
            }
            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }

    public function showUserPayments()
    {
        $this->curUser  = auth()->user();
//        dd($this->curUser->id); // Отладка текущего пользователя


        $totalPaidPrice = DB::table('payments')
            ->where('user_id', $this->curUser->id) // Сначала фильтрация по user_id
            ->sum('payments.summ');      // Затем вычисление суммы



            
//        dd($totalPaidPrice);
        $totalPaidPrice = number_format($totalPaidPrice, 0, '', ' ');


        return view('user.report.payment', ['activeTab' => 'payments'],
            compact('totalPaidPrice'));
    }

    public function getUserPayments(Request $request)
    {
        $this->curUser  = auth()->user();


        if ($request->ajax()) {
            $latestIncomeReceiptSub = FiscalReceipt::query()
                ->select('payment_id', DB::raw('MAX(id) as latest_id'))
                ->whereNotNull('payment_id')
                ->where('type', FiscalReceipt::TYPE_INCOME)
                ->groupBy('payment_id');

            $latestReturnReceiptSub = FiscalReceipt::query()
                ->select('payment_id', DB::raw('MAX(id) as latest_id'))
                ->whereNotNull('payment_id')
                ->where('type', FiscalReceipt::TYPE_INCOME_RETURN)
                ->groupBy('payment_id');

            $payments = Payment::with(['user.team'])
                ->leftJoinSub($latestIncomeReceiptSub, 'latest_income_fiscal_receipts', function ($join) {
                    $join->on('latest_income_fiscal_receipts.payment_id', '=', 'payments.id');
                })
                ->leftJoin('fiscal_receipts as fiscal_income_receipt', 'fiscal_income_receipt.id', '=', 'latest_income_fiscal_receipts.latest_id')
                ->leftJoinSub($latestReturnReceiptSub, 'latest_return_fiscal_receipts', function ($join) {
                    $join->on('latest_return_fiscal_receipts.payment_id', '=', 'payments.id');
                })
                ->leftJoin('fiscal_receipts as fiscal_return_receipt', 'fiscal_return_receipt.id', '=', 'latest_return_fiscal_receipts.latest_id')
                ->leftJoin('payment_intents as pi_tbank', function ($join) {
                    $join->on('payments.partner_id', '=', 'pi_tbank.partner_id')
                        ->where('pi_tbank.provider', '=', 'tbank')
                        ->where(function ($q) {
                            $q->whereNotNull('payments.deal_id')
                                ->orWhereNotNull('payments.payment_id')
                                ->orWhereNotNull('payments.payment_status');
                        })
                        ->whereRaw(
                            'pi_tbank.provider_inv_id = CAST(NULLIF(NULLIF(TRIM(payments.payment_number), ""), "0") AS UNSIGNED)'
                        );
                })
                ->where('payments.user_id', $this->curUser->id) // фильтр по владельцу платежа (payments.*)
                ->select(
                    'payments.*',
                    'fiscal_income_receipt.receipt_url as fiscal_income_receipt_url',
                    'fiscal_return_receipt.receipt_url as fiscal_return_receipt_url',
                    'fiscal_return_receipt.status as fiscal_return_receipt_status',
                    'pi_tbank.payment_method_webhook as intent_payment_method_webhook',
                    'pi_tbank.payment_method as intent_payment_method_init'
                )
                ->get();


            return DataTables::of($payments)
                ->addIndexColumn()
                ->addColumn('user_name', function ($row) {
                    // Проверяем, есть ли пользователь в таблице payments
                    return $row->user_name
                        ? $row->user_name // Возвращаем имя пользователя из payments
                        : ($row->user ? $row->user->name : 'Без пользователя'); // Или из связанной модели, если нет в payments
                })
                ->addColumn('user_id', function ($row) {
                    // Возвращаем user_id, если он существует, иначе null
                    return $row->user ? $row->user->id : null;
                })
                ->addColumn('team_title', function ($row) {
                    // Проверка, существует ли пользователь и его команда
                    return $row->user && $row->user->team
                        ? $row->user->team->title // Возвращаем название команды
                        : 'Без команды'; // Если команды нет
                })
                ->addColumn('summ', function ($row) {
                    return number_format($row->summ, 0) . ' руб'; // Формат суммы
                })
                ->addColumn('operation_date', function ($row) {
                    return $row->operation_date; // Дата операции
                })
                ->addColumn('payment_provider', function ($row) {
                    return (!empty($row->deal_id) || !empty($row->payment_id) || !empty($row->payment_status))
                        ? 'tbank'
                        : 'robokassa';
                })
                ->addColumn('payment_method_label', function ($row) {
                    $code = (string) ($row->intent_payment_method_webhook ?? $row->intent_payment_method_init ?? '');
                    if ($code === '') {
                        return '';
                    }

                    return match ($code) {
                        'card' => 'Карта',
                        'sbp_qr' => 'QR (СБП)',
                        'tpay' => 'T‑Pay',
                        default => $code,
                    };
                })
                ->addColumn('receipt_url', function ($row) {
                    $receiptUrl = trim((string) ($row->fiscal_income_receipt_url ?? ''));
                    if ($receiptUrl === '' || !str_starts_with($receiptUrl, 'https://receipts.ru/')) {
                        return null;
                    }

                    return $receiptUrl;
                })
                ->addColumn('has_receipt', function ($row) {
                    $receiptUrl = trim((string) ($row->fiscal_income_receipt_url ?? ''));
                    return $receiptUrl !== '' && str_starts_with($receiptUrl, 'https://receipts.ru/');
                })
                ->addColumn('return_receipt_url', function ($row) {
                    $receiptUrl = trim((string) ($row->fiscal_return_receipt_url ?? ''));
                    if ($receiptUrl === '' || !str_starts_with($receiptUrl, 'https://receipts.ru/')) {
                        return null;
                    }

                    return $receiptUrl;
                })
                ->addColumn('return_receipt_status', function ($row) {
                    $status = trim((string) ($row->fiscal_return_receipt_status ?? ''));

                    return $status !== '' ? $status : '';
                })
                ->addColumn('refund_status', function ($row) {
                    $refund = Refund::query()
                        ->where('payment_id', $row->id)
                        ->orderByDesc('id')
                        ->first();

                    return $refund ? (string) $refund->status : '';
                })
                ->make(true);
        }
    }

}