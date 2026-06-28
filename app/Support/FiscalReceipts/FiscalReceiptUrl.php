<?php

namespace App\Support\FiscalReceipts;

final class FiscalReceiptUrl
{
    public static function isPublicDisplayUrl(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== '' && str_starts_with($url, 'https://receipts.ru/');
    }
}
