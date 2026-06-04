<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractTemplateRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateEmailRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Models\Partner;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateService;
use App\Services\Contracts\ContractTemplateVariablePresets;
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

        $prefillSources = ContractTemplatePrefillSources::labels();
        $editTemplate = null;
        $editFields = [];
        $openEmailTemplateId = null;

        if ($request->filled('edit')) {
            $editTemplate = ContractTemplate::query()
                ->forPartner($partnerId)
                ->with('currentVersion')
                ->whereKey($request->integer('edit'))
                ->firstOrFail();

            $editFields = ContractTemplateVariablePresets::enrichSchema(
                old('fields')
                    ? array_values(old('fields'))
                    : ($editTemplate->currentVersion?->fields_schema ?? []),
            );
        }

        if ($request->filled('email')) {
            $emailTemplate = ContractTemplate::query()
                ->forPartner($partnerId)
                ->whereKey($request->integer('email'))
                ->exists();

            abort_unless($emailTemplate, 404);

            $openEmailTemplateId = $request->integer('email');
        }

        return view('contract-templates.index', compact(
            'prefillSources',
            'editTemplate',
            'editFields',
            'openEmailTemplateId',
        ) + ['activeTab' => 'templates']);
    }

    public function data(Request $request)
    {
        $partnerId = $this->partner()->id;

        $validated = $request->validate([
            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $baseQuery = ContractTemplate::query()
            ->forPartner($partnerId)
            ->with('currentVersion')
            ->leftJoin(
                'contract_template_versions as current_version',
                'contract_templates.current_version_id',
                '=',
                'current_version.id'
            )
            ->select('contract_templates.*');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseQuery->where(function ($q) use ($like, $search) {
                $q->where('contract_templates.title', 'like', $like);

                if (ctype_digit($search)) {
                    $q->orWhere('contract_templates.id', (int) $search);
                }
            });
        }

        $totalRecords = ContractTemplate::query()
            ->forPartner($partnerId)
            ->count();
        $recordsFiltered = (clone $baseQuery)->count('contract_templates.id');

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'title':
                $baseQuery->orderBy('contract_templates.title', $orderDir)
                    ->orderByDesc('contract_templates.id');
                break;
            case 'version':
                $baseQuery->orderBy('current_version.version', $orderDir)
                    ->orderByDesc('contract_templates.id');
                break;
            case 'fields_count':
                $baseQuery->orderByRaw(
                    'JSON_LENGTH(current_version.fields_schema) ' . $orderDir
                )->orderByDesc('contract_templates.id');
                break;
            case 'status_label':
                $baseQuery->orderBy('contract_templates.is_archived', $orderDir)
                    ->orderByRaw(
                        'CASE WHEN contract_templates.current_version_id IS NULL THEN 1 ELSE 0 END ' . $orderDir
                    )
                    ->orderByDesc('contract_templates.id');
                break;
            case 'id':
            default:
                $baseQuery->orderBy('contract_templates.id', $orderDir);
                break;
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        $templates = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $templates->map(function (ContractTemplate $template) {
            $fieldsSchema = $template->currentVersion?->fields_schema;
            $fieldsCount  = is_array($fieldsSchema) ? count($fieldsSchema) : 0;

            if ($template->is_archived) {
                $statusKey   = 'archived';
                $statusLabel = 'В архиве';
            } elseif ($template->isUsable()) {
                $statusKey   = 'active';
                $statusLabel = 'Активен';
            } else {
                $statusKey   = 'no_version';
                $statusLabel = 'Нет версии';
            }

            return [
                'id'            => $template->id,
                'title'         => $template->title,
                'version'       => $template->currentVersion?->version,
                'fields_count'  => $fieldsCount,
                'status_key'    => $statusKey,
                'status_label'  => $statusLabel,
                'edit_url'      => route('contract-templates.index', ['edit' => $template->id]),
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
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
            'email_subject'   => null,
            'email_body_html' => null,
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
            'is_archived'     => $request->boolean('is_archived'),
        ]);

        return redirect()
            ->route('contract-templates.index')
            ->with('success', 'Шаблон «' . $template->fresh()->title . '» сохранён.');
    }

    public function showEmail(ContractTemplate $template)
    {
        $template->load('currentVersion');

        if (!$template->currentVersion) {
            return response()->json([
                'message' => 'У шаблона нет активной версии.',
            ], 422);
        }

        return response()->json([
            'id'              => $template->id,
            'title'           => $template->title,
            'email_subject'   => $template->currentVersion->resolvedEmailSubject(),
            'email_body_html' => $template->currentVersion->resolvedEmailBodyHtml(),
            'update_url'      => route('contract-templates.update-email', $template),
        ]);
    }

    public function updateEmail(UpdateContractTemplateEmailRequest $request, ContractTemplate $template)
    {
        $validated = $request->validated();

        $this->templateService->updateEmail(
            $template,
            $validated['email_subject'] ?? null,
            $validated['email_body_html'] ?? null,
        );

        $message = 'Письмо для шаблона «' . $template->fresh()->title . '» сохранено.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('contract-templates.index')
            ->with('success', $message);
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
