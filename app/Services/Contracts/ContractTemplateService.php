<?php

namespace App\Services\Contracts;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Partner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractTemplateService
{
    public function __construct(
        private readonly DocxPlaceholderExtractor $placeholderExtractor,
    ) {
    }

    /**
     * @param array{title: string, docx: UploadedFile, fields?: array, email_subject?: string|null, email_body_html?: string|null} $data
     */
    public function create(Partner $partner, array $data): ContractTemplate
    {
        return DB::transaction(function () use ($partner, $data) {
            $template = ContractTemplate::create([
                'partner_id'  => $partner->id,
                'title'       => $data['title'],
                'is_archived' => false,
            ]);

            $version = $this->storeNewVersion(
                $template,
                $data['docx'],
                $data['fields'] ?? null,
                $data['email_subject'] ?? null,
                $data['email_body_html'] ?? null,
                null,
            );

            $template->current_version_id = $version->id;
            $template->save();

            return $template->fresh(['currentVersion']);
        });
    }

    /**
     * @param array{title?: string, docx?: UploadedFile|null, fields?: array, email_subject?: string|null, email_body_html?: string|null, is_archived?: bool} $data
     */
    public function update(ContractTemplate $template, array $data): ContractTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            if (isset($data['title'])) {
                $template->title = $data['title'];
            }

            if (array_key_exists('is_archived', $data)) {
                $template->is_archived = (bool) $data['is_archived'];
            }

            $template->save();

            if (!empty($data['docx']) && $data['docx'] instanceof UploadedFile) {
                $previousSchema = $template->currentVersion?->fields_schema;
                $version = $this->storeNewVersion(
                    $template,
                    $data['docx'],
                    null,
                    $data['email_subject'] ?? $template->currentVersion?->email_subject,
                    $data['email_body_html'] ?? $template->currentVersion?->email_body_html,
                    $previousSchema,
                );

                if (!empty($data['fields'])) {
                    $version->fields_schema = $this->normalizeFieldsInput($data['fields'], $version->fields_schema);
                    $version->save();
                }

                if (array_key_exists('email_subject', $data)) {
                    $version->email_subject = $data['email_subject'];
                }
                if (array_key_exists('email_body_html', $data)) {
                    $version->email_body_html = $data['email_body_html'];
                }
                $version->save();

                $template->current_version_id = $version->id;
                $template->save();
            } elseif ($template->currentVersion && (!empty($data['fields']) || array_key_exists('email_subject', $data) || array_key_exists('email_body_html', $data))) {
                $version = $template->currentVersion;

                if (!empty($data['fields'])) {
                    $version->fields_schema = $this->normalizeFieldsInput(
                        $data['fields'],
                        $version->fields_schema ?? [],
                    );
                }

                if (array_key_exists('email_subject', $data)) {
                    $version->email_subject = $data['email_subject'];
                }
                if (array_key_exists('email_body_html', $data)) {
                    $version->email_body_html = $data['email_body_html'];
                }

                $version->save();
            }

            return $template->fresh(['currentVersion']);
        });
    }

    public function resolveForPartner(int $partnerId, int $templateId): ContractTemplate
    {
        $template = ContractTemplate::query()
            ->forPartner($partnerId)
            ->whereKey($templateId)
            ->first();

        abort_unless($template, 422, 'Шаблон договора не найден.');

        if (!$template->isUsable()) {
            throw ValidationException::withMessages([
                'contract_template_id' => 'Шаблон недоступен: нет активной версии или шаблон в архиве.',
            ]);
        }

        return $template->load('currentVersion');
    }

    private function storeNewVersion(
        ContractTemplate $template,
        UploadedFile $docx,
        ?array $fieldsInput,
        ?string $emailSubject,
        ?string $emailBodyHtml,
        ?array $previousSchema,
    ): ContractTemplateVersion {
        $absolute = $docx->getRealPath() ?: $docx->getPathname();
        if (!$absolute || !is_file($absolute)) {
            throw ValidationException::withMessages([
                'docx' => 'Не удалось прочитать загруженный DOCX.',
            ]);
        }

        try {
            $keys = $this->placeholderExtractor->extractFromPath($absolute);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'docx' => 'Не удалось прочитать плейсхолдеры из DOCX: ' . $e->getMessage(),
            ]);
        }

        if ($keys === []) {
            throw ValidationException::withMessages([
                'docx' => 'В DOCX не найдено плейсхолдеров вида {{имя_поля}}.',
            ]);
        }

        $sha = hash_file('sha256', $absolute);
        $path = $docx->store('contract-templates/' . $template->partner_id . '/' . date('Y/m'));

        $schema = $this->placeholderExtractor->buildFieldsSchema($keys, $previousSchema);

        if ($fieldsInput !== null) {
            $schema = $this->normalizeFieldsInput($fieldsInput, $schema);
        }

        $nextVersion = (int) $template->versions()->max('version') + 1;

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => $nextVersion,
            'docx_path'            => $path,
            'docx_sha256'          => $sha,
            'fields_schema'        => $schema,
            'email_subject'        => $emailSubject,
            'email_body_html'      => $emailBodyHtml,
            'created_by'           => Auth::id(),
        ]);

        return $version;
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @param array<int, array<string, mixed>> $inputRows
     * @return array<int, array<string, mixed>>
     */
    public function normalizeFieldsInput(array $inputRows, array $schema): array
    {
        $allowedKeys = array_column($schema, 'key');
        $byKey = [];
        foreach ($schema as $row) {
            $byKey[$row['key']] = $row;
        }

        foreach ($inputRows as $row) {
            $key = $row['key'] ?? null;
            if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
                continue;
            }

            $byKey[$key]['label'] = trim((string) ($row['label'] ?? $byKey[$key]['label'])) ?: $byKey[$key]['label'];
            $byKey[$key]['required'] = !empty($row['required']);
            $prefill = $row['prefill_source'] ?? null;
            $byKey[$key]['prefill_source'] = is_string($prefill) && $prefill !== '' && in_array($prefill, ContractTemplatePrefillSources::keys(), true)
                ? $prefill
                : null;
        }

        return array_values($byKey);
    }
}
