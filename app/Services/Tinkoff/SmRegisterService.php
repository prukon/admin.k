<?php
//
//namespace App\Services\Tinkoff;
//
//use Illuminate\Support\Facades\Http;
//use Illuminate\Support\Facades\Config;
//
//class SmRegisterService
//{
//private string $base;
//private string $username;
//private string $password;
//private string $cert;
//private string $key;
//
//    public function __construct()
//    {
//        $c = Config::get('tinkoff.acqapi');
//        $this->base     = rtrim($c['base'], '/');
//        $this->username = $c['username'];
//        $this->password = $c['password'];
//        $this->cert     = $c['cert'];
//        $this->key      = $c['key'];
//    }
//
//    private function client()
//    {
//        return Http::withOptions([
//            'cert' => $this->cert,
//            'ssl_key' => $this->key,
//        ]);
//    }
//
//    public function oauthToken(): ?string
//    {
//        $resp = $this->client()->asForm()->post($this->base.'/oauth/token', [
//            'grant_type' => 'password',
//            'username'   => $this->username,
//            'password'   => $this->password,
//        ])->json();
//
//        return $resp['access_token'] ?? null;
//    }
//
//    public function register(array $partner): array
//    {
//        $token = $this->oauthToken();
//        if(!$token){
//            return ['Success'=>false,'Message'=>'OAuth token failed'];
//        }
//
//        $payload = [
//            'name' => $partner['title'],
//            'inn'  => $partner['tax_id'],
//            'kpp'  => $partner['kpp'] ?? null,
//            'ogrn' => $partner['registration_number'] ?? null,
//            'email'=> $partner['email'],
//            'address' => $partner['address'],
//            'bankAccount' => [
//                'account'  => $partner['bank_account'],
//                'bik'      => $partner['bank_bik'],
//                'bankName' => $partner['bank_name'],
//                'correspondentAccount' => $partner['bank_kor_account'] ?? null,
//                'details'  => $partner['bank_payment_details'] ?? null,
//            ],
//            'legalForm' => $partner['legal_form'] === 'company' ? 'LEGAL' : 'IEP',
//        ];
//
//        $resp = $this->client()
//            ->withToken($token)
//            ->asJson()
//            ->post($this->base.'/sm-register/register', $payload)
//            ->json();
//
//        return $resp ?? [];
//    }
//
//    public function status(string $shopCode): array
//    {
//        $token = $this->oauthToken();
//        if(!$token){
//            return ['Success'=>false,'Message'=>'OAuth token failed'];
//        }
//
//        return $this->client()
//                ->withToken($token)
//                ->get($this->base.'/sm-register/register/shop/'.$shopCode)
//                ->json() ?? [];
//    }
//}
