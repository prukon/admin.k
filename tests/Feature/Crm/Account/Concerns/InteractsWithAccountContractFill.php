<?php

namespace Tests\Feature\Crm\Account\Concerns;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait InteractsWithAccountContractFill
{
    protected function useAccountContractFillStorage(): void
    {
        config(['contracts.pdf_converter' => 'fake']);
        config(['queue.default' => 'sync']);

        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_' . (string) Str::uuid();
        @mkdir($root, 0777, true);
        config(['filesystems.disks.local.root' => $root]);
    }

    /**
     * @return array<string, int|bool>
     */
    protected function accountDocumentsSession(): array
    {
        return [
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ];
    }

    protected function getContractFillModalHtml(Contract $contract): string
    {
        return (string) $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');
    }

    /**
     * @param list<array<string, mixed>> $fieldsSchema
     * @param list<string> $docxPlaceholders
     */
    protected function makeAwaitingFillContract(
        array $fieldsSchema,
        array $docxPlaceholders = ['parent_full_name'],
    ): Contract {
        $docxPath = $this->createFillTestDocxOnDisk($docxPlaceholders);

        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Шаблон fill test',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('c', 64),
            'fields_schema'        => $fieldsSchema,
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        return Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $this->user->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);
    }

    /**
     * @param list<string> $fieldKeysInOrder
     */
    protected function assertFillFormFieldOrder(string $html, array $fieldKeysInOrder): void
    {
        $lastPos = -1;
        foreach ($fieldKeysInOrder as $key) {
            $needle = 'name="fields[' . $key . ']"';
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, 'Поле ' . $key . ' не найдено в форме');
            $this->assertGreaterThan($lastPos, $pos, 'Поле ' . $key . ' отображается не в том порядке');
            $lastPos = $pos;
        }
    }

    /**
     * @param list<string> $placeholders
     */
    private function createFillTestDocxOnDisk(array $placeholders): string
    {
        $inner = implode(' ', array_map(static fn (string $key): string => '{{' . $key . '}}', $placeholders));

        $rel = 'contract-templates/fill-test-' . uniqid() . '.docx';
        $abs = Storage::disk()->path($rel);
        @mkdir(dirname($abs), 0775, true);

        $zip = new ZipArchive();
        $zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Договор ' . $inner . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $rel;
    }
}
