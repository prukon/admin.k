<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AccountContractGenerateRequest;
use App\Http\Requests\Contracts\ContractSendRequest;
use App\Models\Contract;
use App\Services\Contracts\ContractPdfGenerationService;
use App\Services\Contracts\ContractPodpislonSendService;
use App\Services\Contracts\ContractPrefillResolver;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountContractFillController extends Controller
{
    public function __construct(
        private readonly ContractPrefillResolver $prefillResolver,
        private readonly ContractPdfGenerationService $pdfGeneration,
        private readonly ContractPodpislonSendService $sendService,
    ) {
    }

    public function show(Request $request, Contract $contract): JsonResponse|RedirectResponse
    {
        abort_unless((int) $contract->user_id === (int) Auth::id(), 404);

        $unavailableMessage = $this->fillUnavailableMessage($contract);
        if ($unavailableMessage !== null) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $unavailableMessage], 422);
            }

            return redirect()
                ->route('account.documents.index')
                ->withErrors(['contract' => $unavailableMessage]);
        }

        $viewData = $this->fillViewData($contract);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Договор #' . $contract->id,
                'html'  => view('account.partials.contract-fill-content', $viewData)->render(),
                'poll'  => $contract->isGeneratingPdf(),
            ]);
        }

        return redirect()->route('account.documents.index', ['fill' => $contract->id]);
    }

    public function generate(AccountContractGenerateRequest $request, Contract $contract): RedirectResponse
    {
        try {
            $this->pdfGeneration->queueClientGeneration(
                $contract,
                $request->fieldValues(),
                Auth::id(),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('account.documents.index', ['fill' => $contract->id])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('account.documents.index', ['fill' => $contract->id])
            ->with('success', 'Договор формируется. Подождите несколько секунд — окно обновится автоматически.');
    }

    public function sign(Contract $contract, ContractSendRequest $request): RedirectResponse
    {
        abort_unless((int) $contract->user_id === (int) Auth::id(), 404);

        try {
            $this->sendService->assertCanClientSign($contract);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('account.documents.index', ['fill' => $contract->id])
                ->withErrors(['contract' => $e->getMessage()]);
        }

        $result = $this->sendService->send($contract, $request->validated(), Auth::id());

        if (!empty($result['success'])) {
            return redirect()
                ->route('account.documents.index')
                ->with('success', $result['message'] ?? 'SMS отправлена.');
        }

        return redirect()
            ->route('account.documents.index', ['fill' => $contract->id])
            ->withErrors(['sign' => $result['message'] ?? 'Не удалось отправить SMS.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fillViewData(Contract $contract): array
    {
        $contract->load(['templateVersion.template', 'user', 'team']);

        $schema = $contract->templateVersion?->fields_schema ?? [];
        $schema = ContractTemplateVariablePresets::schemaFieldsForParentForm($schema);
        $prefill = $this->prefillResolver->resolveForContract($contract, $schema);
        $prefill = ContractTemplateVariablePresets::applySplitNamePrefill(array_merge(
            $prefill,
            $contract->clientFormFieldValues(),
        ));

        $signerDefaults = $this->prefillResolver->resolveSignerParts(
            $contract->user,
            is_array($contract->filled_data) ? $contract->filled_data : []
        );

        return [
            'contract'              => $contract,
            'fields'                => $schema,
            'fieldGroups'           => ContractTemplateVariablePresets::groupFieldsForParentForm($schema),
            'prefill'               => $prefill,
            'prefillSources'        => \App\Services\Contracts\ContractTemplatePrefillSources::labels(),
            'signerDefaults'        => $signerDefaults,
            'generationError'       => $contract->pdfGenerationError(),
            'showContractFieldKeys' => Gate::allows('account.contracts.showFieldKeys'),
        ];
    }

    private function fillUnavailableMessage(Contract $contract): ?string
    {
        $contract->load(['templateVersion.template', 'user', 'team']);

        if ($contract->isFillExpired() && $contract->status === Contract::STATUS_AWAITING_CLIENT_FILL) {
            return 'Срок заполнения договора истёк. Обратитесь в организацию.';
        }

        if (!$contract->canClientFill() && !$contract->canClientSign() && !$contract->isGeneratingPdf()) {
            return 'Договор недоступен для заполнения.';
        }

        return null;
    }
}
