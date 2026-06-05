<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

class GenerateContractPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    /**
     * @param array<string, string> $fieldInput
     */
    public function __construct(
        public int $contractId,
        public array $fieldInput,
        public ?int $authorId = null,
    ) {
    }

    public function handle(ContractPdfGenerationService $pdfGeneration): void
    {
        $contract = Contract::query()->find($this->contractId);
        if (!$contract || $contract->status !== Contract::STATUS_GENERATING_PDF) {
            return;
        }

        try {
            $pdfGeneration->generateFromClientForm(
                $contract,
                $this->fieldInput,
                $this->authorId,
                fromQueue: true,
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?? 'Не удалось сформировать PDF. Обратитесь в организацию.';

            $pdfGeneration->failGeneration($this->contractId, (string) $message, $this->fieldInput, $this->authorId);
        } catch (\Throwable $e) {
            report($e);

            $pdfGeneration->failGeneration(
                $this->contractId,
                self::userFacingGenerationError($e),
                $this->fieldInput,
                $this->authorId,
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        $contract = Contract::query()->find($this->contractId);
        if (!$contract || $contract->status !== Contract::STATUS_GENERATING_PDF) {
            return;
        }

        app(ContractPdfGenerationService::class)->failGeneration(
            $this->contractId,
            self::userFacingGenerationError($e),
            $this->fieldInput,
            $this->authorId,
        );
    }

    private static function userFacingGenerationError(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'Не удалось сформировать PDF. Обратитесь в организацию.';
        }

        if (str_contains($message, 'Исходный DOCX не найден')
            || str_contains($message, 'Файл шаблона DOCX не найден')) {
            return 'Файл шаблона договора не найден на сервере. Обратитесь в организацию.';
        }

        if (str_contains($message, 'LibreOffice') || str_contains($message, 'конверт')) {
            return 'Не удалось конвертировать договор в PDF. Обратитесь в организацию.';
        }

        return 'Не удалось сформировать PDF. Обратитесь в организацию.';
    }
}
