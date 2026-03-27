<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Services\Signatures\Providers\PodpislonProvider;
use PHPUnit\Framework\TestCase;

class PodpislonProviderMapDocumentStatusTest extends TestCase
{
    /**
     * @dataProvider numericAndLegacyCodesProvider
     */
    public function test_maps_openapi_numeric_codes_and_legacy_strings(
        string|int|null $apiStatus,
        ?string $expectedContractStatus
    ): void {
        $this->assertSame(
            $expectedContractStatus,
            PodpislonProvider::mapDocumentStatusToContract($apiStatus, null)
        );
    }

    public static function numericAndLegacyCodesProvider(): array
    {
        return [
            '10 created' => ['10', Contract::STATUS_DRAFT],
            '15 sent string' => ['15', Contract::STATUS_SENT],
            '15 sent int' => [15, Contract::STATUS_SENT],
            '20 viewed' => ['20', Contract::STATUS_OPENED],
            '30 signed string' => ['30', Contract::STATUS_SIGNED],
            '30 signed int' => [30, Contract::STATUS_SIGNED],
            '40 revoked' => ['40', Contract::STATUS_REVOKED],
            'legacy sent' => ['sent', Contract::STATUS_SENT],
            'legacy opened' => ['opened', Contract::STATUS_OPENED],
            'legacy signed' => ['signed', Contract::STATUS_SIGNED],
            'unknown code 35' => ['35', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    public function test_maps_status_text_fallback_when_code_unknown(): void
    {
        $this->assertSame(
            Contract::STATUS_SIGNED,
            PodpislonProvider::mapDocumentStatusToContract('99', 'Подписан')
        );

        $this->assertSame(
            Contract::STATUS_OPENED,
            PodpislonProvider::mapDocumentStatusToContract(null, 'Просмотрен')
        );
    }
}
