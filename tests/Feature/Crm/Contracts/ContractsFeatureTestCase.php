<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;
use ZipArchive;

abstract class ContractsFeatureTestCase extends CrmTestCase
{
    protected const PERM_CONTRACTS_VIEW = 'contracts.view';

    protected const PERM_CONTRACTS_SYNC = 'contracts.sync';

    protected function setUp(): void
    {
        parent::setUp();

        // В некоторых окружениях storage/ может быть read-only.
        // Storage::fake() использует storage_path('framework/testing/...') — поэтому переносим storage_path в /tmp.
        $storage = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_'
            . (string) Str::uuid();

        if (!is_dir($storage)) {
            @mkdir($storage, 0777, true);
        }
        @chmod($storage, 0777);

        $appStorage = $storage . DIRECTORY_SEPARATOR . 'app';
        if (!is_dir($appStorage)) {
            @mkdir($appStorage, 0777, true);
        }
        @chmod($appStorage, 0777);

        $this->app->useStoragePath($storage);
        config(['filesystems.disks.local.root' => $appStorage]);

        // Страхуемся от 2FA-редиректов в окружениях, где 2FA может быть включена.
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        // Для большинства тестов нужен доступ к разделу "Договоры".
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * @param list<string> $placeholders
     */
    protected function fakeDocxUploadedFile(array $placeholders = ['fio_parent']): UploadedFile
    {
        $inner = implode(' ', array_map(static fn (string $key) => '{{' . $key . '}}', $placeholders));

        $path = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Текст ' . $inner . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return new UploadedFile(
            $path,
            'template.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    protected function createContractTemplateWithVersion(array $templateAttrs = [], array $versionAttrs = []): ContractTemplate
    {
        $template = ContractTemplate::create(array_merge([
            'partner_id'  => $this->partner->id,
            'title'       => 'Тестовый шаблон',
            'is_archived' => false,
        ], $templateAttrs));

        $docxPath = $versionAttrs['docx_path'] ?? 'contract-templates/test-' . uniqid() . '.docx';
        if (!isset($versionAttrs['docx_path'])) {
            Storage::put($docxPath, $this->minimalDocxBytes(['fio_parent']));
        }

        $version = ContractTemplateVersion::create(array_merge([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('a', 64),
            'fields_schema'        => [
                ['key' => 'fio_parent', 'label' => 'ФИО родителя', 'required' => true, 'prefill_source' => null],
            ],
            'email_subject'   => 'Заполните договор',
            'email_body_html' => '<p>Текст письма</p>',
        ], $versionAttrs));

        $template->current_version_id = $version->id;
        $template->save();

        return $template->fresh(['currentVersion']);
    }

    /**
     * @param list<string> $placeholders
     */
    protected function minimalDocxBytes(array $placeholders = ['fio_parent']): string
    {
        $inner = implode(' ', array_map(static fn (string $key) => '{{' . $key . '}}', $placeholders));
        $path = tempnam(sys_get_temp_dir(), 'docx_bytes_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . $inner . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}

