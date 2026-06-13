<?php

namespace App\Http\Controllers\Contracts;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\FilterRequest;
use App\Http\Requests\Contracts\StoreContractTemplateRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateEmailRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Partner;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateService;
use App\Services\Contracts\ContractTemplateVariablePresets;
use App\Support\BuildsLogTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractTemplateController extends Controller
{
    use BuildsLogTable;

    public function __construct(
        private readonly ContractTemplateService $templateService,
        private readonly AuditLogger $auditLogger,
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

        $template->load('currentVersion');

        $this->auditLogger->record(
            AuditEvent::ContractTemplateCreated,
            AuditContext::make($this->formatContractTemplateCreatedDescription($template))
                ->withTarget($template, $template->title)
                ->withAuthorId($request->user()?->id)
                ->withPartnerId($this->partner()->id)
                ->withCreatedAt(Carbon::now())
        );

        return redirect()
            ->route('contract-templates.index')
            ->with('success', 'Шаблон «' . $template->title . '» создан.');
    }

    public function edit(Request $request, ContractTemplate $template)
    {
        if (!$request->ajax() && !$request->expectsJson()) {
            return redirect()->route('contract-templates.index', ['edit' => $template->id]);
        }

        $template->load('currentVersion');

        $editFields = ContractTemplateVariablePresets::enrichSchema(
            $template->currentVersion?->fields_schema ?? [],
        );

        return response()->json([
            'id'          => $template->id,
            'title'       => $template->title,
            'update_url'  => route('contract-templates.update', $template),
            'html'        => view('contract-templates.partials.edit-form', [
                'template'       => $template,
                'fields'         => $editFields,
                'prefillSources' => ContractTemplatePrefillSources::labels(),
            ])->render(),
        ]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $template)
    {
        $validated = $request->validated();

        $template->load('currentVersion');
        $beforeSnapshot = $this->contractTemplateAuditSnapshot($template);

        $this->templateService->update($template, [
            'title'           => $validated['title'],
            'docx'            => $request->file('docx'),
            'fields'          => $validated['fields'] ?? null,
            'is_archived'     => $request->boolean('is_archived'),
        ]);

        $template->refresh()->load('currentVersion');

        $changes = $this->diffContractTemplateAuditSnapshots(
            $beforeSnapshot,
            $this->contractTemplateAuditSnapshot($template),
        );

        if ($changes !== []) {
            $this->auditLogger->record(
                AuditEvent::ContractTemplateUpdated,
                AuditContext::make(implode("\n", $changes))
                    ->withTarget($template, $this->contractTemplateAuditLabel($template))
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($this->partner()->id)
                    ->withCreatedAt(Carbon::now())
            );
        }

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

        $template->load('currentVersion');
        $beforeSnapshot = $this->contractTemplateEmailAuditSnapshot($template->currentVersion);

        $this->templateService->updateEmail(
            $template,
            $validated['email_subject'] ?? null,
            $validated['email_body_html'] ?? null,
        );

        $template->refresh()->load('currentVersion');

        $changes = $this->diffContractTemplateEmailSnapshots(
            $beforeSnapshot,
            $this->contractTemplateEmailAuditSnapshot($template->currentVersion),
        );

        if ($changes !== []) {
            $this->auditLogger->record(
                AuditEvent::ContractTemplateEmailUpdated,
                AuditContext::make(implode("\n", $changes))
                    ->withTarget($template, $this->contractTemplateAuditLabel($template))
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($this->partner()->id)
                    ->withCreatedAt(Carbon::now())
            );
        }

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

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('contract_template');
    }

    private function contractTemplateAuditLabel(ContractTemplate $template): string
    {
        return "Шаблон #{$template->id}: {$template->title}";
    }

    private function formatContractTemplateCreatedDescription(ContractTemplate $template): string
    {
        $fieldsCount = is_array($template->currentVersion?->fields_schema)
            ? count($template->currentVersion->fields_schema)
            : 0;

        return "Шаблон договора создан: #{$template->id} «{$template->title}», версия "
            . ($template->currentVersion?->version ?? '—')
            . ", полей {$fieldsCount}.";
    }

    /**
     * @return array{title: string, is_archived: string, version: string, fields_schema: array<int, array<string, mixed>>}
     */
    private function contractTemplateAuditSnapshot(ContractTemplate $template): array
    {
        $template->loadMissing('currentVersion');
        $fieldsSchema = $template->currentVersion?->fields_schema;

        return [
            'title'         => (string) ($template->title ?? ''),
            'is_archived'   => $template->is_archived ? 'В архиве' : 'Активен',
            'version'       => (string) ($template->currentVersion?->version ?? '—'),
            'fields_schema' => is_array($fieldsSchema) ? $fieldsSchema : [],
        ];
    }

    /**
     * @return array{email_subject: string, email_body_html: string}
     */
    private function contractTemplateEmailAuditSnapshot(?ContractTemplateVersion $version): array
    {
        return [
            'email_subject'   => $this->auditTextValue($version?->email_subject, 'не задана'),
            'email_body_html' => $this->auditEmailBodyValue($version?->email_body_html),
        ];
    }

    /**
     * @param  array{title: string, is_archived: string, version: string, fields_schema: array<int, array<string, mixed>>}  $before
     * @param  array{title: string, is_archived: string, version: string, fields_schema: array<int, array<string, mixed>>}  $after
     * @return list<string>
     */
    private function diffContractTemplateAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'title'       => 'Название',
            'is_archived' => 'Статус',
            'version'     => 'Версия DOCX',
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return array_merge(
            $changes,
            $this->diffFieldsSchema($before['fields_schema'] ?? [], $after['fields_schema'] ?? []),
        );
    }

    /**
     * @param  array{email_subject: string, email_body_html: string}  $before
     * @param  array{email_subject: string, email_body_html: string}  $after
     * @return list<string>
     */
    private function diffContractTemplateEmailSnapshots(array $before, array $after): array
    {
        $changes = [];

        if (($before['email_subject'] ?? '') !== ($after['email_subject'] ?? '')) {
            $changes[] = "Тема письма: {$before['email_subject']} → {$after['email_subject']}";
        }

        if (($before['email_body_html'] ?? '') !== ($after['email_body_html'] ?? '')) {
            $changes[] = "Тело письма: {$before['email_body_html']} → {$after['email_body_html']}";
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     * @return list<string>
     */
    private function diffFieldsSchema(array $before, array $after): array
    {
        $beforeByKey = $this->indexFieldsSchema($before);
        $afterByKey  = $this->indexFieldsSchema($after);
        $allKeys     = array_values(array_unique(array_merge(array_keys($beforeByKey), array_keys($afterByKey))));
        sort($allKeys);

        $prefillLabels = ContractTemplatePrefillSources::labels();
        $changes       = [];

        foreach ($allKeys as $key) {
            $beforeField = $beforeByKey[$key] ?? null;
            $afterField  = $afterByKey[$key] ?? null;

            if ($beforeField === null && $afterField !== null) {
                $changes[] = "Поле {$key}: добавлено";
                continue;
            }

            if ($beforeField !== null && $afterField === null) {
                $changes[] = "Поле {$key}: удалено";
                continue;
            }

            if ($beforeField === null || $afterField === null) {
                continue;
            }

            foreach (['label', 'required', 'prefill_source', 'fill_sort_order'] as $attribute) {
                $beforeValue = $this->fieldSchemaDisplayValue($beforeField, $attribute, $prefillLabels);
                $afterValue  = $this->fieldSchemaDisplayValue($afterField, $attribute, $prefillLabels);

                if ($beforeValue === $afterValue) {
                    continue;
                }

                $attributeLabel = match ($attribute) {
                    'label'            => 'подпись',
                    'required'         => 'обязательность',
                    'prefill_source'   => 'prefill',
                    'fill_sort_order'  => 'порядок заполнения',
                    default            => $attribute,
                };

                $changes[] = "Поле {$key}, {$attributeLabel}: {$beforeValue} → {$afterValue}";
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function indexFieldsSchema(array $schema): array
    {
        $indexed = [];

        foreach ($schema as $field) {
            $key = ContractTemplateVariablePresets::canonicalFieldKey((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $indexed[$key] = $field;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fieldSchemaDisplayValue(array $field, string $attribute, array $prefillLabels): string
    {
        return match ($attribute) {
            'label' => trim((string) ($field['label'] ?? '')) !== ''
                ? trim((string) $field['label'])
                : '—',
            'required' => ! empty($field['required']) ? 'Да' : 'Нет',
            'prefill_source' => isset($field['prefill_source'])
                && is_string($field['prefill_source'])
                && $field['prefill_source'] !== ''
                ? ($prefillLabels[$field['prefill_source']] ?? $field['prefill_source'])
                : '—',
            'fill_sort_order' => array_key_exists('fill_sort_order', $field)
                && $field['fill_sort_order'] !== null
                && $field['fill_sort_order'] !== ''
                ? (string) $field['fill_sort_order']
                : '—',
            default => '',
        };
    }

    private function auditTextValue(mixed $value, string $emptyLabel): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $emptyLabel;
    }

    private function auditEmailBodyValue(?string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($html ?? ''))) ?? '');

        if ($text === '') {
            return 'не задано';
        }

        if (mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117) . '…';
        }

        return $text;
    }
}
