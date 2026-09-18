<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tinkoff;

use App\Services\Tinkoff\TinkoffPayoutBankError;
use PHPUnit\Framework\TestCase;

final class TinkoffPayoutBankErrorTest extends TestCase
{
    public function test_from_payload_formats_e2c_init_reject(): void
    {
        $text = TinkoffPayoutBankError::fromPayload([
            'Success' => false,
            'ErrorCode' => '-937',
            'Message' => 'Возмещения партнера заблокированы',
            'Details' => 'Операция отклонена из-за блокировки возмещений партнера.',
        ]);

        $this->assertSame(
            'Код -937. Возмещения партнера заблокированы. Операция отклонена из-за блокировки возмещений партнера.',
            $text
        );
    }

    public function test_from_payload_ignores_successful_response(): void
    {
        $this->assertNull(TinkoffPayoutBankError::fromPayload([
            'Success' => true,
            'ErrorCode' => '0',
            'Status' => 'CHECKED',
        ]));
    }

    public function test_from_payload_maps_local_reject_reasons(): void
    {
        $this->assertSame(
            'Сумма к выплате 0 ₽ после комиссий',
            TinkoffPayoutBankError::fromPayload(['rejected_reason' => 'net_amount_zero'])
        );
        $this->assertSame(
            'Выплата отменена из‑за возврата',
            TinkoffPayoutBankError::fromPayload(['cancelled_by_refund' => true])
        );
    }
}
