<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplateVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractPdfGenerationService
{
    public function __construct(
        private readonly ContractDocxPlaceholderFiller $filler,
        private readonly ContractPdfConverterInterface $pdfConverter,
        private readonly ContractPrefillResolver $prefillResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldInput keyed by field key
     */
    public function generateFromClientForm(Contract $contract, array $fieldInput): Contract
    {
        $this->assertCanGenerate($contract);

        $contract->loadMissing(['templateVersion', 'user', 'team']);
        /** @var ContractTemplateVersion|null $version */
        $version = $contract->templateVersion;
        if (!$version || !$version->docx_path) {
            throw ValidationException::withMessages([
                'contract' => 'Шаблон договора не найден.',
            ]);
        }

        $schema = $version->fields_schema ?? [];
        $this->validateFieldInput($schema, $fieldInput);

        $prefill = $this->prefillResolver->resolveForContract($contract, $schema);
        $values = array_merge(
            $this->prefillResolver->mergeInput($prefill, $fieldInput),
            ContractTemplateSystemPlaceholders::forContract($contract),
        );

        return DB::transaction(function () use ($contract, $version, $values) {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->assertCanGenerate($locked);

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
                Storage::put($storagePdfPath, file_get_contents($pdfAbsolute) ?: '');
                $sha = hash_file('sha256', Storage::path($storagePdfPath));

                $locked->filled_data = $values;
                $locked->source_pdf_path = $storagePdfPath;
                $locked->source_sha256 = $sha;
                $locked->status = Contract::STATUS_DRAFT;
                $locked->save();

                ContractEvent::create([
                    'contract_id'  => $locked->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'pdf_generated_by_client',
                    'payload_json' => json_encode([
                        'template_version_id' => $version->id,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

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

    public function assertCanGenerate(Contract $contract): void
    {
        if (!$contract->isTemplateMode()) {
            throw ValidationException::withMessages([
                'contract' => 'Этот договор не создан из шаблона.',
            ]);
        }

        if ($contract->status !== Contract::STATUS_AWAITING_CLIENT_FILL) {
            throw ValidationException::withMessages([
                'contract' => 'Договор уже сформирован или недоступен для заполнения.',
            ]);
        }

        if ($contract->fill_expires_at && $contract->fill_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'contract' => 'Срок заполнения договора истёк. Обратитесь в организацию.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @param array<string, mixed> $input
     */
    public function validateFieldInput(array $schema, array $input): void
    {
        $errors = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
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
