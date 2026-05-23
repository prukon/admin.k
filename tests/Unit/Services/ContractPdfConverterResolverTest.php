<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\ContractPdfConverterResolver;
use App\Services\Contracts\FakeContractPdfConverter;
use App\Services\Contracts\LibreOfficePdfConverter;
use App\Services\Contracts\PhpWordDompdfConverter;
use Tests\TestCase;

class ContractPdfConverterResolverTest extends TestCase
{
    /** @test */
    public function auto_uses_phpword_when_proc_open_missing(): void
    {
        config(['contracts.pdf_converter' => 'auto']);

        $resolver = new class extends ContractPdfConverterResolver {
            public function canRunProcesses(): bool
            {
                return false;
            }
        };

        $this->assertInstanceOf(PhpWordDompdfConverter::class, $resolver->resolve());
    }

    /** @test */
    public function explicit_phpword_driver(): void
    {
        config(['contracts.pdf_converter' => 'phpword']);

        $converter = (new ContractPdfConverterResolver())->resolve();

        $this->assertInstanceOf(PhpWordDompdfConverter::class, $converter);
    }
}
