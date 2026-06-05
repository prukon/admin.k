<?php

namespace App\Services\Contracts;

use App\Enums\AuditEvent;
use App\Jobs\GenerateContractPdfJob;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplateVersion;
use App\Services\Audit\ContractAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractPdfGenerationService
{
    public const GENERATION_ERROR_KEY = '_generation_error';

    public function __construct(
        private readonly ContractDocxPlaceholderFiller $filler,
        private readonly ContractPdfConverterInterface $pdfConverter,
        private readonly ContractPrefillResolver $prefillResolver,
        private readonly ContractParentProfileSyncService $parentProfileSync,
        private readonly ContractStudentProfileSyncService $studentProfileSync,
        private readonly ContractAudit $contractAudit,
    ) {
    }

    /**
     * Валидация и постановка генерации PDF в очередь (LibreOffice в worker CLI).
     *
     * @param array<string, mixed> $fieldInput keyed by field key
     */
    public function queueClientGeneration(Contract $contract, array $fieldInput, ?int $authorId = null): void
    {
        $this->assertCanClientSubmitFields($contract);

        $contract->loadMissing('templateVersion');
        $schema = ContractTemplateVariablePresets::schemaFieldsForParentForm(
            $contract->templateVersion?->fields_schema ?? [],
        );
        $fieldInput = ContractTemplateVariablePresets::composeNameFieldsForPdf($fieldInput);
        $this->assertTemplateDocxAvailable($contract);
        $this->validateFieldInput($schema, $fieldInput);

        $authorId = $authorId ?? Auth::id();

        DB::transaction(function () use ($contract, $fieldInput, $authorId) {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->assertCanClientSubmitFields($locked);

            $locked->filled_data = self::stripInternalFilledDataKeys($fieldInput);
            $locked->status = Contract::STATUS_GENERATING_PDF;
            $locked->save();

            GenerateContractPdfJob::dispatch($locked->id, $fieldInput, $authorId);
        });
    }

    /**
     * @param array<string, mixed> $fieldInput keyed by field key
     */
    public function generateFromClientForm(
        Contract $contract,
        array $fieldInput,
        ?int $authorId = null,
        bool $fromQueue = false,
    ): Contract {
        $this->assertCanClientSubmitFields($contract, $fromQueue);

        $contract->loadMissing(['templateVersion', 'user', 'team']);
        /** @var ContractTemplateVersion|null $version */
        $version = $contract->templateVersion;
        if (!$version || !$version->docx_path) {
            throw ValidationException::withMessages([
                'contract' => 'Шаблон договора не найден.',
            ]);
        }

        $this->assertTemplateDocxAvailable($contract);

        $schema = ContractTemplateVariablePresets::schemaFieldsForParentForm($version->fields_schema ?? []);
        $fieldInput = ContractTemplateVariablePresets::composeNameFieldsForPdf(
            is_array($fieldInput) ? $fieldInput : [],
        );
        $this->validateFieldInput($schema, $fieldInput);

        $prefill = $this->prefillResolver->resolveForContract($contract, $schema);
        $prefill = ContractTemplateVariablePresets::applySplitNamePrefill($prefill);
        $values = ContractTemplateVariablePresets::composeNameFieldsForPdf(
            ContractTemplateVariablePresets::expandDocxPlaceholderValues(array_merge(
                $this->prefillResolver->mergeInput($prefill, $fieldInput),
                ContractTemplateSystemPlaceholders::forContract($contract),
            )),
        );

        $authorId = $authorId ?? Auth::id();

        return DB::transaction(function () use ($contract, $version, $values, $authorId) {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->assertCanClientSubmitFields($locked, true);

            $oldPdfPath = is_string($locked->source_pdf_path) && $locked->source_pdf_path !== ''
                ? $locked->source_pdf_path
                : null;
            $isRegeneration = $oldPdfPath !== null;

            $docxSource = Storage::path($version->docx_path);
            $workDir = storage_path('app/contracts/tmp/' . $locked->id . '_' . Str::uuid());
            if (!is_dir($workDir) && !@mkdir($workDir, 0775, true) && !is_dir($workDir)) {
                throw new \RuntimeException('Не удалось создать рабочий каталог.');
            }

            try {
                $filledDocx = $workDir . DIRECTORY_SEPARATOR . 'filled.docx';
                $this->filler->fill($docxSource, $filledDocx, $values);
                $pdfAbsolute = $this->pdfConverter->convertDocxToPdf($filledDocx, $workDir);

                $storagePdfPath = 'documents/' . date('Y/m') . '/contract-' . $locked->id . '-filled.pdf';

                if ($isRegeneration && $oldPdfPath !== null && Storage::exists($oldPdfPath)) {
                    Storage::delete($oldPdfPath);
                }

                Storage::put($storagePdfPath, file_get_contents($pdfAbsolute) ?: '');
                $sha = hash_file('sha256', Storage::path($storagePdfPath));

                $locked->filled_data = $values;
                $locked->source_pdf_path = $storagePdfPath;
                $locked->source_sha256 = $sha;
                $locked->status = Contract::STATUS_DRAFT;
                $locked->save();

                ContractEvent::create([
                    'contract_id'  => $locked->id,
                    'author_id'    => $authorId,
                    'type'         => $isRegeneration ? 'pdf_regenerated_by_client' : 'pdf_generated_by_client',
                    'payload_json' => json_encode([
                        'template_version_id' => $version->id,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                if ($isRegeneration) {
                    $this->contractAudit->record(
                        AuditEvent::ContractPdfRegeneratedByClient,
                        "Родитель изменил данные договора и перегенерировал PDF\n"
                        . 'Договор № ' . $locked->id,
                        userId: (int) $locked->user_id,
                        authorId: $authorId,
                        partnerId: (int) $locked->school_id,
                        contract: $locked,
                    );
                }

                $locked->loadMissing('user');
                if ($locked->user) {
                    $this->parentProfileSync->syncFromFilledData(
                        $locked->user,
                        (int) $locked->school_id,
                        $values,
                    );
                    $this->studentProfileSync->syncFromFilledData($locked->user, $values);
                }

                return $locked->fresh();
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'contract' => 'Не удалось сформировать PDF. Обратитесь в организацию. (' . $e->getMessage() . ')',
                ]);
            } finally {
                $this->removeDirectory($workDir);
            }
        });
    }

    /**
     * @param array<string, mixed> $fieldInput
     */
    public function failGeneration(int $contractId, string $message, array $fieldInput, ?int $authorId = null): void
    {
        DB::transaction(function () use ($contractId, $message, $fieldInput, $authorId) {
            $locked = Contract::query()->whereKey($contractId)->lockForUpdate()->first();
            if (!$locked || $locked->status !== Contract::STATUS_GENERATING_PDF) {
                return;
            }

            $data = self::stripInternalFilledDataKeys($fieldInput);
            $data[self::GENERATION_ERROR_KEY] = $message;

            $locked->filled_data = $data;
            $locked->status = !empty($locked->source_pdf_path)
                ? Contract::STATUS_DRAFT
                : Contract::STATUS_AWAITING_CLIENT_FILL;
            $locked->save();

            ContractEvent::create([
                'contract_id'  => $locked->id,
                'author_id'    => $authorId,
                'type'         => 'pdf_generation_failed',
                'payload_json' => json_encode([
                    'message' => $message,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function assertCanClientSubmitFields(Contract $contract, bool $fromQueue = false): void
    {
        if (!$contract->isTemplateMode()) {
            throw ValidationException::withMessages([
                'contract' => 'Этот договор не создан из шаблона.',
            ]);
        }

        if ($fromQueue) {
            if ($contract->status !== Contract::STATUS_GENERATING_PDF) {
                throw ValidationException::withMessages([
                    'contract' => 'Договор уже сформирован или недоступен для заполнения.',
                ]);
            }

            return;
        }

        if ($contract->canClientFill()) {
            if ($contract->fill_expires_at && $contract->fill_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'contract' => 'Срок заполнения договора истёк. Обратитесь в организацию.',
                ]);
            }

            if ($contract->status !== Contract::STATUS_AWAITING_CLIENT_FILL) {
                throw ValidationException::withMessages([
                    'contract' => 'Договор уже сформирован или недоступен для заполнения.',
                ]);
            }

            return;
        }

        if ($contract->canClientEditFilledData()) {
            if ($contract->status !== Contract::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'contract' => 'Договор недоступен для изменения.',
                ]);
            }

            return;
        }

        if ($contract->isClientEditExpired() && $contract->isTemplateMode()
            && $contract->status === Contract::STATUS_DRAFT
            && !empty($contract->source_pdf_path)
            && empty($contract->provider_doc_id)
        ) {
            throw ValidationException::withMessages([
                'contract' => 'Срок изменения данных договора истёк. Обратитесь в организацию.',
            ]);
        }

        throw ValidationException::withMessages([
            'contract' => 'Договор уже сформирован или недоступен для заполнения.',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function stripInternalFilledDataKeys(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $value) {
            if (!is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    public function assertTemplateDocxAvailable(Contract $contract): void
    {
        $contract->loadMissing('templateVersion');
        $path = $contract->templateVersion?->docx_path;

        if (!is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'contract' => 'Шаблон договора не найден.',
            ]);
        }

        if (!Storage::exists($path)) {
            throw ValidationException::withMessages([
                'contract' => 'Файл шаблона DOCX не найден на сервере. Обратитесь в организацию.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @param array<string, mixed> $input
     */
    public function validateFieldInput(array $schema, array $input): void
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized[$key] = trim((string) $value);
        }

        $input = ContractTemplateVariablePresets::composeNameFieldsForPdf($normalized);
        $errors = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (ContractTemplateVariablePresets::isSystemFillField($key)) {
                continue;
            }

            $label = (string) ($field['label'] ?? $key);
            $required = !empty($field['required']);
            $value = trim((string) ($input[$key] ?? ''));

            if ($required && $value === '') {
                $errors['fields.' . $key] = 'Поле «' . $label . '» обязательно для заполнения.';
            }

            if ($value !== '' && mb_strlen($value) > 2000) {
                $errors['fields.' . $key] = 'Поле «' . $label . '» слишком длинное.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
