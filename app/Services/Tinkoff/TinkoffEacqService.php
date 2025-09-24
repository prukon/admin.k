<?php
//
//namespace App\Services\Tinkoff;
//
//use Illuminate\Support\Facades\Http;
//use Illuminate\Support\Facades\Config;
//
//class TinkoffEacqService
//{
//private string $base;
//private string $terminal;
//private string $password;
//
//    public function __construct()
//    {
//        $cfg = Config::get('tinkoff.eacq');
//        $this->base     = rtrim($cfg['base'], '/');
//        $this->terminal = $cfg['terminal'];
//        $this->password = $cfg['password'];
//    }
//
//    private function token(array $payload): string
//    {
//        $data = array_change_key_case($payload + [
//                'TerminalKey' => $this->terminal,
//                'Password'    => $this->password,
//            ], CASE_LOWER);
//        ksort($data);
//        return hash('sha256', implode('', $data));
//    }
//
//    public function init(array $params): array
//    {
//        $payload = array_merge($params, [
//            'TerminalKey'   => $this->terminal,
//            'NotificationURL' => config('tinkoff.eacq.callback_url'),
//            'SuccessURL'    => config('tinkoff.eacq.success_url'),
//            'FailURL'       => config('tinkoff.eacq.fail_url'),
//            'PayType'       => 'O', // одностадийная
//        ]);
//        $payload['Token'] = $this->token($payload);
//
//        $r = Http::asJson()->post($this->base.'/v2/Init', $payload)->json();
//        return $r ?? [];
//    }
//
//    public function getState(string $paymentId): array
//    {
//        $payload = [
//            'TerminalKey' => $this->terminal,
//            'PaymentId'   => $paymentId,
//        ];
//        $payload['Token'] = $this->token($payload);
//        return Http::asJson()->post($this->base.'/v2/GetState', $payload)->json() ?? [];
//    }
//}
