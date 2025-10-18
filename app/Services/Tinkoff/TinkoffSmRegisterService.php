<?php

namespace App\Services\Tinkoff;

use Illuminate\Support\Facades\Config;

class TinkoffSmRegisterService
{
    protected function token(): string
    {
        $cfg = Config::get('tinkoff.sm_register');

        $payload = [
            'grant_type' => 'password',
            'username'   => $cfg['login'],
            'password'   => $cfg['password'],
        ];

        $cert = [
            'cert' => $cfg['mtls']['cert'],
            'key'  => $cfg['mtls']['key'],
            'ca'   => $cfg['mtls']['ca'],
        ];

        $resp = TinkoffApiClient::post($cfg['base_url'], $cfg['oauth_path'], $payload, $cert);
        if (empty($resp['access_token'])) {
            throw new \RuntimeException('sm-register OAuth failed');
        }
        return $resp['access_token'];
    }

    public function registerPartner(array $data): array
    {
        $cfg = Config::get('tinkoff.sm_register');
        $token = $this->token();

        $payload = $data; // см. доку sm-register: реквизиты+bankAccount.details
        $payload['Authorization'] = 'Bearer '.$token;

        $cert = [
            'cert' => $cfg['mtls']['cert'],
            'key'  => $cfg['mtls']['key'],
            'ca'   => $cfg['mtls']['ca'],
        ];

        return TinkoffApiClient::post($cfg['base_url'], '/sm-register/register', $payload, $cert);
    }

    public function patchPartner(string $shopCode, array $data): array
    {
        $cfg = Config::get('tinkoff.sm_register');
        $token = $this->token();

        $payload = $data;
        $payload['Authorization'] = 'Bearer '.$token;

        $cert = [
            'cert' => $cfg['mtls']['cert'],
            'key'  => $cfg['mtls']['key'],
            'ca'   => $cfg['mtls']['ca'],
        ];

        return TinkoffApiClient::post($cfg['base_url'], "/sm-register/register/{$shopCode}", $payload, $cert);
    }
}
