<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Services\Signatures\PodpislonSigningUrl;
use PHPUnit\Framework\TestCase;

final class PodpislonSigningUrlTest extends TestCase
{
    public function test_normalize_accepts_pack_url_and_strips_trailing_slash(): void
    {
        $url = 'https://podpislon.ru/sign/pack/971890/9f1d11872060/';

        $this->assertSame(
            'https://podpislon.ru/sign/pack/971890/9f1d11872060',
            PodpislonSigningUrl::normalize($url)
        );
    }

    public function test_normalize_rejects_foreign_host_and_empty(): void
    {
        $this->assertNull(PodpislonSigningUrl::normalize(''));
        $this->assertNull(PodpislonSigningUrl::normalize(null));
        $this->assertNull(PodpislonSigningUrl::normalize('https://evil.example/sign/pack/1/abc'));
        $this->assertNull(PodpislonSigningUrl::normalize('javascript:alert(1)'));
        $this->assertNull(PodpislonSigningUrl::normalize('https://podpislon.ru/lk/document/1'));
    }

    public function test_from_document_reads_contacts_link(): void
    {
        $doc = [
            'status' => 15,
            'contacts' => [
                ['phone' => '+79991112233', 'link' => 'https://podpislon.ru/sign/pack/971890/9f1d11872060'],
            ],
        ];

        $this->assertSame(
            'https://podpislon.ru/sign/pack/971890/9f1d11872060',
            PodpislonSigningUrl::fromDocument($doc)
        );
    }

    public function test_from_document_reads_add_document_links_and_raw_list(): void
    {
        $this->assertSame(
            'https://podpislon.ru/sign/pack/1/abc',
            PodpislonSigningUrl::fromDocument([
                'ids' => [101],
                'links' => ['https://podpislon.ru/sign/pack/1/abc'],
            ])
        );

        $this->assertSame(
            'https://podpislon.ru/sign/pack/2/def',
            PodpislonSigningUrl::fromDocument([
                'raw' => [
                    ['status' => 15, 'contacts' => [['link' => 'https://podpislon.ru/sign/pack/2/def']]],
                ],
            ])
        );
    }

    public function test_from_links_picks_first_valid(): void
    {
        $this->assertSame(
            'https://podpislon.ru/sign/pack/3/aaa',
            PodpislonSigningUrl::fromLinks([
                ['link' => 'https://example.com/nope'],
                ['link' => 'https://podpislon.ru/sign/pack/3/aaa'],
            ])
        );
    }

    public function test_persist_ignores_invalid_and_same_value(): void
    {
        $contract = new Contract();
        $contract->provider_signing_url = null;

        $this->assertFalse(PodpislonSigningUrl::persist($contract, 'https://example.com/x'));
        $this->assertNull($contract->provider_signing_url);
    }
}
