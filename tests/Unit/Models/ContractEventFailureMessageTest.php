<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ContractEvent;
use PHPUnit\Framework\TestCase;

final class ContractEventFailureMessageTest extends TestCase
{
    public function test_extracts_podpislon_raw_message_from_legacy_failed_payload(): void
    {
        $json = json_encode([
            'res' => [
                'provider_doc_id' => null,
                'raw' => [
                    'status' => false,
                    'message' => 'Не выбран провайдер отправки смс.',
                    'sessError' => '',
                ],
            ],
            'links' => [],
        ], JSON_UNESCAPED_UNICODE);

        $this->assertSame(
            'Не выбран провайдер отправки смс.',
            ContractEvent::messageFromPayloadJson($json)
        );
    }

    public function test_prefers_top_level_message_over_nested_raw(): void
    {
        $json = json_encode([
            'message' => 'Короткий текст для админа',
            'res' => [
                'raw' => ['message' => 'Вложенный'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->assertSame('Короткий текст для админа', ContractEvent::messageFromPayloadJson($json));
    }

    public function test_reads_exception_error_key(): void
    {
        $json = json_encode(['error' => 'PODPISLON: файл не найден'], JSON_UNESCAPED_UNICODE);

        $this->assertSame('PODPISLON: файл не найден', ContractEvent::messageFromPayloadJson($json));
    }

    public function test_from_provider_send_result_falls_back_to_generic(): void
    {
        $this->assertSame(
            ContractEvent::PROVIDER_SEND_NOT_CONFIRMED,
            ContractEvent::fromProviderSendResult(['provider_doc_id' => null])
        );
    }

    public function test_from_provider_send_result_reads_raw_message(): void
    {
        $this->assertSame(
            'Не выбран провайдер отправки смс.',
            ContractEvent::fromProviderSendResult([
                'raw' => ['status' => false, 'message' => 'Не выбран провайдер отправки смс.'],
            ])
        );
    }

    public function test_empty_payload_and_blank_message_are_not_user_facing(): void
    {
        $this->assertNull(ContractEvent::messageFromPayloadJson(null));
        $this->assertNull(ContractEvent::messageFromPayloadJson(''));
        $this->assertNull(ContractEvent::messageFromPayloadJson('{"res":{"ok":false}}'));
        $this->assertNull(ContractEvent::messageFromPayloadJson('{"message":"   "}'));
    }
}
