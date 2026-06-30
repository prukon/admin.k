<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Partner\PartnerDataTableRequest;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Http\Requests\Team\FilterRequest;


use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use App\Services\TeamService;
use App\Services\UserService;
use Carbon\Carbon;

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Support\BuildsLogTable;
use App\Support\PartnerLegacyLegalFields;
use App\Services\PartnerContext;

//Контроллер для админа

class PartnerController extends AdminBaseController
{
    use BuildsLogTable;

    protected TeamService $service;

    public function __construct(
        TeamService $service,
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    )
    {
        parent::__construct($partnerContext);
        $this->service = $service;
    }

    public function index()
    {
        return view('admin.partners.index', [
            'activeTab' => 'partners',
        ]);
    }

    public function data(PartnerDataTableRequest $request)
    {
        $validated = $request->validated();

        $baseQuery = Partner::query();

        $titleSearch = trim((string) ($validated['title'] ?? ''));
        if ($titleSearch === '' && $request->filled('search.value')) {
            $titleSearch = trim((string) $request->input('search.value'));
        }

        if ($titleSearch !== '') {
            $like = '%' . $titleSearch . '%';
            $baseQuery->where(function ($q) use ($like, $titleSearch) {
                $q->where('title', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                if (ctype_digit($titleSearch)) {
                    $q->orWhere('id', (int) $titleSearch);
                }
            });
        }

        $status = $validated['status'] ?? null;
        if ($status === 'active') {
            $baseQuery->where('is_enabled', 1);
        } elseif ($status === 'inactive') {
            $baseQuery->where('is_enabled', 0);
        }

        $totalRecords = Partner::query()->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'order_by':
                $baseQuery->orderBy('order_by', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'title':
                $baseQuery->orderBy('title', $orderDir);
                break;
            case 'email':
                $baseQuery->orderBy('email', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'phone':
                $baseQuery->orderBy('phone', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'status_label':
                $baseQuery->orderBy('is_enabled', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'rownum':
            case 'actions':
            default:
                $baseQuery->orderBy('order_by', 'asc')
                    ->orderBy('title', 'asc');
                break;
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $partners = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $partners->map(function (Partner $partner) {
            return [
                'id'                 => $partner->id,
                'order_by'           => $partner->order_by,
                'title'              => $partner->title,
                'email'              => $partner->email ?? '',
                'phone'              => $partner->phone ?? '',
                'status_label'       => $partner->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'         => (int) $partner->is_enabled,
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StorePartnerRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();
        $data = PartnerLegacyLegalFields::strip($request->validated());

        $partner = null;

        DB::transaction(function () use ($data, $authorId, $partnerId, &$partner) {
            $partner = Partner::create($data);

            $fields = [
                'title' => 'Название школы/секции',
                'sms_name' => 'Название для SMS/выписок',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $lines = [];
            foreach ($fields as $key => $label) {
                $val = $partner->{$key} ?? '—';
                if ($key === 'is_enabled') $val = $val ? 'Да' : 'Нет';
                $lines[] = "{$label}: {$val}";
            }

            $this->auditLogger->record(
                AuditEvent::PartnerCreated,
                AuditContext::make("Создан новый партнёр:\n" . implode("\n", $lines))
                    ->withTarget($partner, $partner->title)
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Партнёр успешно создан',
                'partner' => $partner,
            ], 201);
        }

        return redirect()
            ->route('admin.partner.index')
            ->with('ok', 'Партнёр успешно создан');
    }

    public function edit(Partner $partner)
    {
        $payload = [
            'id' => $partner->id,
            'title' => $partner->title,
            'sms_name' => $partner->sms_name,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'website' => $partner->website,
            'order_by' => $partner->order_by,
            'is_enabled' => (bool)$partner->is_enabled,
        ];

        return response()->json($payload);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();
        $data = PartnerLegacyLegalFields::strip($request->validated());

        DB::transaction(function () use ($data, $authorId, $partnerId, $partner) {

            $old = $partner->only([
                'title', 'sms_name',
                'phone', 'email', 'website',
                'order_by', 'is_enabled',
            ]);

            $partner->update($data);

            $new = $partner->only([
                'title', 'sms_name',
                'phone', 'email', 'website',
                'order_by', 'is_enabled',
            ]);

            $fields = [
                'title' => 'Название школы/секции',
                'sms_name' => 'Название для SMS/выписок',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $oldLines = [];
            $newLines = [];

            foreach ($fields as $key => $label) {
                $ov = $old[$key] ?? '—';
                $nv = $new[$key] ?? '—';
                if ($key === 'is_enabled') {
                    $ov = $ov ? 'Да' : 'Нет';
                    $nv = $nv ? 'Да' : 'Нет';
                }
                if ((string)$ov !== (string)$nv) {
                    $oldLines[] = "{$label}: {$ov}";
                    $newLines[] = "{$label}: {$nv}";
                }
            }


            if ($oldLines) {
                // собираем пары "старое → новое"
                $changes = [];
                foreach ($oldLines as $i => $oldLine) {
                    $label = explode(':', $oldLine, 2)[0] ?? '';
                    $oldVal = trim(explode(':', $oldLine, 2)[1] ?? '—');
                    $newVal = trim(explode(':', $newLines[$i] ?? '', 2)[1] ?? '—');
                    $changes[] = "{$label}: {$oldVal} → {$newVal}";
                }

                // переносы строк после каждой пары
                $description = implode(";\n", $changes) . "\n";

            //Изменение партнера
                $this->auditLogger->record(
                    AuditEvent::PartnerUpdatedBySuperadmin,
                    AuditContext::make($description)
                        ->withTarget($partner, $partner->title)
                        ->withAuthorId($authorId)
                        ->withPartnerId($partnerId)
                        ->withCreatedAt(now())
                );
            }

            Log::info('[Partner.update] updated', [
                'partner_id' => $partner->id,
                'after' => $new,
            ]);
        });

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Партнёр успешно обновлён',
                'partner' => $partner,
            ], 200);
        }

        return redirect()
            ->route('admin.partner.index')
            ->with('ok', 'Партнёр успешно обновлён');
    }

    public function destroy(Partner $partner)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();

        // Собираем данные партнёра перед удалением
        $old = $partner->only([
            'title',
            'sms_name',
            'phone',
            'email',
            'website',
            'order_by',
            'is_enabled',
        ]);

        DB::transaction(function () use ($partner, $old, $authorId, $partnerId) {
            // Удаляем партнёра
            $partner->delete();

            // Формируем читаемую строку старых значений
            $fields = [
                'title' => 'Название школы/секции',
                'sms_name' => 'Название для SMS/выписок',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $lines = [];
            foreach ($fields as $key => $label) {
                $val = $old[$key] ?? '—';
                if ($key === 'is_enabled') {
                    $val = $val ? 'Да' : 'Нет';
                }
                $lines[] = "{$label}: {$val}";
            }

            // Запись лога удаления
            $this->auditLogger->record(
                AuditEvent::PartnerDeleted,
                AuditContext::make("Удалён партнёр:\n" . implode("\n", $lines))
                    ->withTarget($partner, $partner->title)
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'message' => 'Партнёр удалён',
        ], 200);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('partner');
    }
}
