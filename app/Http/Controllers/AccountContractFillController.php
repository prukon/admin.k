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

        $fillMode = $request->string('mode')->toString() === 'edit' ? 'edit' : 'default';

        if ($fillMode === 'edit') {
            $editUnavailableMessage = $this->editUnavailableMessage($contract);
            if ($editUnavailableMessage !== null) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['message' => $editUnavailableMessage], 422);
                }

                return redirect()
                    ->route('account.documents.index')
                    ->withErrors(['contract' => $editUnavailableMessage]);
            }
        }

        $unavailableMessage = $this->fillUnavailableMessage($contract, $fillMode);
        if ($unavailableMessage !== null) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $unavailableMessage], 422);
            }

            return redirect()
                ->route('account.documents.index')
                ->withErrors(['contract' => $unavailableMessage]);
        }

        $viewData = $this->fillViewData($contract, $fillMode);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Договор #' . $contract->id,
                'html'  => view('account.partials.contract-fill-content', $viewData)->render(),
                'poll'  => $contract->isGeneratingPdf(),
            ]);
        }

        return redirect()->route('account.documents.index', [
            'fill' => $contract->id,
            'mode' => $fillMode === 'edit' ? 'edit' : null,
        ]);
    }

    public function generate(AccountContractGenerateRequest $request, Contract $contract): RedirectResponse|JsonResponse
    {
        $wantsJson = $request->ajax() || $request->wantsJson();
        $isRegeneration = $contract->canClientEditFilledData();
        $fillRouteParams = ['fill' => $contract->id];
        if ($isRegeneration) {
            $fillRouteParams['mode'] = 'edit';
        }

        try {
            $this->pdfGeneration->queueClientGeneration(
                $contract,
                $request->fieldValues(),
                Auth::id(),
            );
        } catch (ValidationException $e) {
            if ($wantsJson) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first() ?? 'Проверьте заполнение полей.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            return redirect()
                ->route('account.documents.index', $fillRouteParams)
                ->withErrors($e->errors())
                ->withInput();
        }

        $contract->refresh();

        if ($contract->status === Contract::STATUS_AWAITING_CLIENT_FILL && $contract->pdfGenerationError() !== null) {
            $errorMessage = $contract->pdfGenerationError();

            if ($wantsJson) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors'  => ['contract' => [$errorMessage]],
                ], 422);
            }

            return redirect()
                ->route('account.documents.index', $fillRouteParams)
                ->withErrors(['contract' => $errorMessage])
                ->withInput();
        }

        if ($contract->isGeneratingPdf()) {
            $message = $isRegeneration
                ? 'Договор обновляется. Подождите несколько секунд — окно обновится автоматически.'
                : 'Договор формируется. Подождите несколько секунд — окно обновится автоматически.';
        } elseif ($isRegeneration) {
            $message = 'Данные сохранены. PDF обновлён.';
        } else {
            $message = 'Договор сформирован.';
        }

        if ($wantsJson) {
            return response()->json([
                'message' => $message,
                'poll'    => $contract->isGeneratingPdf(),
            ]);
        }

        return redirect()
            ->route('account.documents.index', $fillRouteParams)
            ->with('success', $message);
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
    private function fillViewData(Contract $contract, string $fillMode = 'default'): array
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
            'fillMode'              => $fillMode,
        ];
    }

    private function editUnavailableMessage(Contract $contract): ?string
    {
        $contract->load(['templateVersion.template', 'user', 'team']);

        if ($contract->isGeneratingPdf()) {
            return null;
        }

        if ($contract->canClientEditFilledData()) {
            return null;
        }

        if ($contract->isTemplateMode()
            && $contract->status === Contract::STATUS_DRAFT
            && !empty($contract->source_pdf_path)
            && empty($contract->provider_doc_id)
            && $contract->isClientEditExpired()
        ) {
            return 'Срок изменения данных договора истёк. Обратитесь в организацию.';
        }

        if (!empty($contract->provider_doc_id)) {
            return 'Договор уже отправлен на подпись и недоступен для изменения.';
        }

        return 'Договор недоступен для изменения.';
    }

    private function fillUnavailableMessage(Contract $contract, string $fillMode = 'default'): ?string
    {
        $contract->load(['templateVersion.template', 'user', 'team']);

        if ($fillMode === 'edit') {
            if ($contract->isGeneratingPdf() || $contract->canClientEditFilledData()) {
                return null;
            }

            return 'Договор недоступен для изменения.';
        }

        if ($contract->isFillExpired() && $contract->status === Contract::STATUS_AWAITING_CLIENT_FILL) {
            return 'Срок заполнения договора истёк. Обратитесь в организацию.';
        }

        if (!$contract->canClientFill() && !$contract->canClientSign() && !$contract->isGeneratingPdf()) {
            return 'Договор недоступен для заполнения.';
        }

        return null;
    }
}
