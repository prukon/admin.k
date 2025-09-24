<?php
//
//namespace App\Services\Tinkoff;
//
//use Illuminate\Support\Facades\Http;
//use Illuminate\Support\Facades\Config;
//
//class TinkoffA2cService
//{
//private string $base;
//private string $terminal;
//private string $password;
//
//    public function __construct()
//    {
//        $cfg = Config::get('tinkoff.a2c');
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
//    public function initPayout(array $params): array
//    {
//        $payload = array_merge($params, [
//            'TerminalKey' => $this->terminal,
//        ]);
//        $payload['Token'] = $this->token($payload);
//
//        return Http::asJson()->post($this->base.'/e2c/v2/Init', $payload)->json() ?? [];
//    }
//
//    public function payment(string $paymentId): array
//    {
//        $payload = [
//            'TerminalKey' => $this->terminal,
//            'PaymentId'   => $paymentId,
//        ];
//        $payload['Token'] = $this->token($payload);
//        return Http::asJson()->post($this->base.'/e2c/v2/Payment', $payload)->json() ?? [];
//    }
//
//    public function getState(string $paymentId): array
//    {
//        $payload = [
//            'TerminalKey' => $this->terminal,
//            'PaymentId'   => $paymentId,
//        ];
//        $payload['Token'] = $this->token($payload);
//        return Http::asJson()->post($this->base.'/e2c/v2/GetState', $payload)->json() ?? [];
//    }
//}
