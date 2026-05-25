<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractTemplateRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Models\Partner;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractTemplateController extends Controller
{
    public function __construct(
        private readonly ContractTemplateService $templateService,
    ) {
    }

    private function partner(): Partner
    {
        $p = app('current_partner');
        abort_unless($p, 403, 'Партнёр не выбран.');

        return $p;
    }

    public function index(Request $request)
    {
        $partnerId = $this->partner()->id;

        $templates = ContractTemplate::query()
            ->forPartner($partnerId)
            ->with('currentVersion')
            ->orderByDesc('id')
            ->paginate(20);

        $prefillSources = ContractTemplatePrefillSources::labels();
        $editTemplate = null;
        $editFields = [];

        if ($request->filled('edit')) {
            $editTemplate = ContractTemplate::query()
                ->forPartner($partnerId)
                ->with('currentVersion')
                ->whereKey($request->integer('edit'))
                ->firstOrFail();

            $editFields = old('fields')
                ? array_values(old('fields'))
                : ($editTemplate->currentVersion?->fields_schema ?? []);
        }

        return view('contract-templates.index', compact(
            'templates',
            'prefillSources',
            'editTemplate',
            'editFields',
        ) + ['activeTab' => 'templates']);
    }

    public function create()
    {
        return redirect()->route('contract-templates.index', ['create' => 1]);
    }

    public function store(StoreContractTemplateRequest $request)
    {
        $validated = $request->validated();

        $template = $this->templateService->create($this->partner(), [
            'title'           => $validated['title'],
            'docx'            => $request->file('docx'),
            'fields'          => $validated['fields'] ?? null,
            'email_subject'   => $validated['email_subject'] ?? null,
            'email_body_html' => $validated['email_body_html'] ?? null,
        ]);

        return redirect()
            ->route('contract-templates.index')
            ->with('success', 'Шаблон «' . $template->title . '» создан.');
    }

    public function edit(ContractTemplate $template)
    {
        return redirect()->route('contract-templates.index', ['edit' => $template->id]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $template)
    {
        $validated = $request->validated();

        $this->templateService->update($template, [
            'title'           => $validated['title'],
            'docx'            => $request->file('docx'),
            'fields'          => $validated['fields'] ?? null,
            'email_subject'   => $validated['email_subject'] ?? null,
            'email_body_html' => $validated['email_body_html'] ?? null,
            'is_archived'     => $request->boolean('is_archived'),
        ]);

        return redirect()
            ->route('contract-templates.index')
            ->with('success', 'Шаблон «' . $template->fresh()->title . '» сохранён.');
    }

    public function downloadDocx(ContractTemplate $template)
    {
        $template->load('currentVersion');
        $path = $template->currentVersion?->docx_path;

        if (!$path || !Storage::exists($path)) {
            return back()->withErrors(['docx' => 'DOCX-файл шаблона не найден.']);
        }

        $filename = 'template-' . $template->id . '-v' . ($template->currentVersion->version ?? 1) . '.docx';

        return Storage::download($path, $filename);
    }
}
