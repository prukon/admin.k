<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SmsRuService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SmsRuServiceUserFacingErrorTest extends TestCase
{
    #[DataProvider('gatewayResultsProvider')]
    public function test_user_facing_error_message(string $gatewayResult, string $expected): void
    {
        $this->assertSame($expected, SmsRuService::userFacingErrorMessage($gatewayResult));
    }

    public static function gatewayResultsProvider(): array
    {
        $operator = SmsRuService::USER_ERROR_OPERATOR_NOT_CONNECTED;
        $generic = SmsRuService::USER_ERROR_GENERIC;
        $unavailable = SmsRuService::USER_ERROR_GATEWAY_UNAVAILABLE;

        return [
            'empty' => ['', $generic],
            'empty api_id' => ['Empty api_id', $generic],
            'empty phone' => ['Empty phone', $generic],
            'bare API error' => ['API error', $generic],
            'unknown API' => ['API error: Unknown error', $generic],
            'http exception' => ['HTTP exception: cURL error 28', $unavailable],
            'http status' => ['HTTP error: 502', $unavailable],
            '204 with code suffix' => [
                'SMS error: Вы не подключили данного оператора на данном отправителе. [204]',
                $operator,
            ],
            '204 by russian text without code' => [
                'SMS error: Вы не подключили данного оператора на данном отправителе (а также запасном или отправителе по умолчанию). Подайте заявку через раздел *Отправители*.',
                $operator,
            ],
            'other status text' => [
                'SMS error: Недостаточно средств на счете [202]',
                'Не удалось отправить SMS: Недостаточно средств на счете',
            ],
            'global api text' => [
                'API error: Неправильный api_id [200]',
                'Не удалось отправить SMS: Неправильный api_id',
            ],
        ];
    }
}
