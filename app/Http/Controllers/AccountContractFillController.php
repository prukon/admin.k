<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AccountContractGenerateRequest;
use App\Http\Requests\Contracts\ContractSendRequest;
use App\Models\Contract;
use App\Services\Contracts\ContractPdfGenerationService;
use App\Services\Contracts\ContractPodpislonSendService;
use App\Services\Contracts\ContractPrefillResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountContractFillController extends Controller
{
    public function __construct(
        private readonly ContractPrefillResolver $prefillResolver,
        private readonly ContractPdfGenerationService $pdfGeneration,
        private readonly ContractPodpislonSendService $sendService,
    ) {
    }

    public function show(Contract $contract)
    {
        abort_unless((int) $contract->user_id === (int) Auth::id(), 404);

        $contract->load(['templateVersion.template', 'user', 'team']);

        if ($contract->isFillExpired() && $contract->status === Contract::STATUS_AWAITING_CLIENT_FILL) {
            return redirect()
                ->route('account.documents.index')
                ->withErrors(['contract' => 'Срок заполнения договора истёк. Обратитесь в организацию.']);
        }

        if (!$contract->canClientFill() && !$contract->canClientSign()) {
            return redirect()
                ->route('account.documents.index')
                ->withErrors(['contract' => 'Договор недоступен для заполнения.']);
        }

        $schema = $contract->templateVersion?->fields_schema ?? [];
        $prefill = $this->prefillResolver->resolveForContract($contract, $schema);

        $signerDefaults = $this->prefillResolver->resolveSignerParts(
            $contract->user,
            is_array($contract->filled_data) ? $contract->filled_data : []
        );

        return view('account.contract-fill', [
            'contract'       => $contract,
            'fields'         => $schema,
            'prefill'        => $prefill,
            'signerDefaults' => $signerDefaults,
        ]);
    }

    public function generate(AccountContractGenerateRequest $request, Contract $contract)
    {
        try {
            $this->pdfGeneration->generateFromClientForm($contract, $request->fieldValues());
        } catch (ValidationException $e) {
            return redirect()
                ->route('account.documents.fill', $contract)
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('account.documents.fill', $contract)
            ->with('success', 'Договор сформирован. Проверьте PDF и отправьте SMS на подпись.');
    }

    public function sign(Contract $contract, ContractSendRequest $request)
    {
        abort_unless((int) $contract->user_id === (int) Auth::id(), 404);

        try {
            $this->sendService->assertCanClientSign($contract);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('account.documents.fill', $contract)
                ->withErrors(['contract' => $e->getMessage()]);
        }

        $result = $this->sendService->send($contract, $request->validated(), Auth::id());

        if (!empty($result['success'])) {
            return redirect()
                ->route('account.documents.index')
                ->with('success', $result['message'] ?? 'SMS отправлена.');
        }

        return redirect()
            ->route('account.documents.fill', $contract)
            ->withErrors(['sign' => $result['message'] ?? 'Не удалось отправить SMS.']);
    }
}
