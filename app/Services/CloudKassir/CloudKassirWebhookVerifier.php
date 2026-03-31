<?php

namespace App\Services\CloudKassir;

class CloudKassirWebhookVerifier
{
    public function isValid(string $rawBody, ?string $xContentHmac, ?string $contentHmac): bool
    {
        $secret = (string) config('services.cloudkassir.api_secret', '');
        if ($secret === '') {
            return false;
        }

        if (!$xContentHmac && !$contentHmac) {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        if ($xContentHmac && hash_equals($calculated, $xContentHmac)) {
            return true;
        }

        if ($contentHmac && hash_equals($calculated, $contentHmac)) {
            return true;
        }

        return false;
    }
}