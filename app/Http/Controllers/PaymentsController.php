<?php
//
//namespace App\Http\Controllers;
//
//use App\Models\Deal;
//use App\Models\Partner;
//use App\Models\AcqPayment; // ⬅️ ВАЖНО: новая модель
//use App\Services\Tinkoff\TinkoffEacqService;
//use App\Services\Tinkoff\TinkoffA2cService;
//use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Log;
//
//class PaymentsController extends Controller
//{
//    public function init(Request $r, TinkoffEacqService $eacq)
//    {
//        // вход: partner_id, order_id, amount, user_id (optional)
//        $r->validate([
//            'partner_id' => 'required|integer|exists:partners,id',
//            'order_id'   => 'required|string',
//            'amount'     => 'required|numeric|min:1',
//        ]);
//
//        $partner = Partner::findOrFail($r->partner_id);
//
//        // создаём сделку (локально)
//        $deal = Deal::create([
//            'partner_id' => $partner->id,
//            'status'     => 'open',
//            'opened_at'  => now(),
//        ]);
//
//        // Init в банке
//        $init = $eacq->init([
//            'OrderId'     => $r->order_id,
//            'Amount'      => (int) round($r->amount * 100),
//            'Description' => 'Оплата услуг: '.$r->order_id,
//            'DATA' => [
//                'CreateDealWithType' => 'NN',       // сделка при оплате
//                'DealID'             => $deal->id,  // свой ID для связи
//            ],
//            'PayType' => 'O', // одностадийная
//        ]);
//
//        if (!($init['Success'] ?? false)) {
//            return response()->json(['ok'=>false,'error'=>$init['Message'] ?? 'Init failed'], 422);
//        }
//
//        // Регистрируем платёж локально в acq_payments
//        $acq = AcqPayment::create([
//            'deal_id'    => $deal->id,
//            'user_id'    => $r->user_id,
//            'order_id'   => $r->order_id,
//            'amount'     => $r->amount,
//            'payment_id' => $init['PaymentId'] ?? null,
//            'status'     => 'new',
//            'payload'    => $init,
//        ]);
//
//        return response()->json([
//            'ok'          => true,
//            'payment_url' => $init['PaymentURL'] ?? $init['PaymentUrl'] ?? null,
//            'payment_id'  => $acq->payment_id,
//        ]);
//    }
//
//    // NotificationURL из банка
//    public function callback(Request $r, TinkoffA2cService $a2c)
//    {
//        Log::info('[callback] payload', $r->all());
//
//        $paymentId = $r->get('PaymentId');
//        $status    = $r->get('Status'); // CONFIRMED/CANCELED/...
//
//        // Ищем в нашей новой таблице
//        $acq = AcqPayment::where('payment_id', $paymentId)->first();
//        if (!$acq) {
//            return response('NO', 404);
//        }
//
//        // Обновляем локальный статус оплаты
//        if ($status === 'CONFIRMED') {
//            $acq->status = 'confirmed';
//            $acq->save();
//
//            // Комиссия платформы → считаем нетто
//            $deal    = $acq->deal;
//            $partner = $deal->partner;
//
//            [$fee, $net] = $this->calcPlatformFee($partner, (float)$acq->amount);
//
//            // если партнёр зарегистрирован в sm-register → запускаем выплату (без FinalPayout в грейс)
//            if ($partner->sm_shop_code) {
//                $init = $a2c->initPayout([
//                    'ShopCode'   => $partner->sm_shop_code,
//                    'Amount'     => (int) round($net * 100),
//                    'OrderId'    => 'payout-'.$acq->id,
//                    'Description
