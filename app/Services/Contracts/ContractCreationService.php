<?php

namespace App\Services\Contracts;

use App\Mail\ContractClientFillInvitationMail;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplateVersion;
use App\Models\Partner;
use App\Models\User;
use App\Enums\AuditEvent;
use App\Services\Audit\ContractAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContractCreationService
{
    public function __construct(
        private readonly ContractBillingService $billing,
        private readonly ContractTemplateService $templateService,
        private readonly ContractAudit $contractAudit,
    ) {
    }

    /**
     * @param array{user_id: int, creation_mode: string, pdf?: UploadedFile, contract_template_id?: int} $data
     */
    public function create(Partner $partner, array $data): Contract
    {
        $student = User::query()
            ->where('id', $data['user_id'])
            ->where('partner_id', $partner->id)
            ->where('is_enabled', 1)
            ->first();

        abort_unless($student, 422, 'Ученик не найден у текущего партнёра.');

        $mode = $data['creation_mode'];

        return DB::transaction(function () use ($partner, $student, $data, $mode) {
            if ($mode === Contract::CREATION_MODE_PDF) {
                $contract = $this->createPdfContract($partner, $student, $data['pdf']);
            } else {
                $contract = $this->createTemplateContract($partner, $student, (int) $data['contract_template_id']);
            }

            $this->billing->chargeCreationFee($partner, $contract);

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'created',
                'payload_json' => json_encode([
                    'creation_mode' => $contract->creation_mode,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->contractAudit->record(
                AuditEvent::ContractCreated,
                'Договор создан: № ' . $contract->id . ' (' . $contract->creation_mode . ')',
                userId: (int) $student->id,
                partner: $partner,
            );

            if ($contract->isTemplateMode()) {
                $this->notifyClientAboutFill($contract, $student);
            }

            return $contract;
        });
    }

    private function createPdfContract(Partner $partner, User $student, UploadedFile $pdf): Contract
    {
        $path = $pdf->store('documents/' . date('Y/m'));
        $sha = hash_file('sha256', Storage::path($path));

        return Contract::create([
            'school_id'                   => $partner->id,
            'user_id'                     => $student->id,
            'group_id'                    => $student->team_id,
            'creation_mode'               => Contract::CREATION_MODE_PDF,
            'contract_template_version_id'=> null,
            'source_pdf_path'             => $path,
            'source_sha256'               => $sha,
            'status'                      => Contract::STATUS_DRAFT,
            'provider'                    => 'podpislon',
        ]);
    }

    private function createTemplateContract(Partner $partner, User $student, int $templateId): Contract
    {
        $template = $this->templateService->resolveForPartner($partner->id, $templateId);
        /** @var ContractTemplateVersion $version */
        $version = $template->currentVersion;

        return Contract::create([
            'school_id'                    => $partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => $student->team_id,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'filled_data'                  => null,
            'fill_expires_at'              => now()->addDays(Contract::FILL_TTL_DAYS),
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'provider'                     => 'podpislon',
        ]);
    }

    private function notifyClientAboutFill(Contract $contract, User $student): void
    {
        $contract->loadMissing('templateVersion.template');

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => Auth::id(),
            'type'         => 'client_invited_to_fill',
            'payload_json' => json_encode([
                'email' => $student->email,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $email = trim((string) ($student->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new ContractClientFillInvitationMail($contract, $student));
        } catch (\Throwable $e) {
            Log::warning('[contracts] client fill invitation email failed', [
                'contract_id' => $contract->id,
                'user_id'     => $student->id,
                'error'       => $e->getMessage(),
            ]);

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'client_invite_email_failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}
