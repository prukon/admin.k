<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserSex;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\UserCardLogsRequest;
use App\Models\Contract;
use App\Models\MyLog;
use App\Models\OutgoingEmailLog;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\PartnerContext;
use App\Services\Pricing\UserPercentDiscount;
use App\Services\TeamUserSyncService;
use App\Services\TrainerOwnTeamsScope;
use App\Services\Users\StudentCardAccess;
use App\Services\Users\StudentCardLoginHints;
use App\Support\AuditLogQueryScopes;
use App\Support\Payments\PaymentTeamTitleDisplay;
use App\Support\RuPhone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class UserCardController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly StudentCardAccess $access,
        private readonly TrainerOwnTeamsScope $ownTeams,
        private readonly StudentCardLoginHints $loginHints,
    ) {
        parent::__construct($partnerContext);
    }

    public function show(Request $request, User $user): Response|RedirectResponse
    {
        $student = $this->cardUserOrAbort($user);
        $partnerId = $this->requirePartnerId();
        $actor = $this->currentUser();
        $isStudent = $student->role?->name === 'user';

        if (! $request->ajax()) {
            return redirect()->route('admin.user1', ['card' => $student->id]);
        }

        $student->loadMissing('parentProfile');
        $parentFields = $isStudent ? $student->parentFormFields() : [];

        return response()
            ->view('admin.users.show', [
            'student' => $student,
            'header' => $this->header($student),
            'infoRows' => $this->infoRows($student, $actor),
            'isStudent' => $isStudent,
            'groups' => $isStudent ? $this->groups($student, $actor, $partnerId) : [],
            'teamOptions' => $isStudent ? $this->teamOptions($actor, $partnerId) : [],
            'selectedTeamIds' => $isStudent ? $this->selectedTeamIds($student, $actor, $partnerId) : [],
            'customFields' => $this->customFields($student, $actor, $partnerId),
            'hasParent' => $isStudent && (int) ($student->parent_id ?? 0) > 0 && $student->parentProfile !== null,
            'hasParentProfiles' => $isStudent && ParentProfile::query()->where('partner_id', $partnerId)->exists(),
            'parentFields' => $parentFields,
            'loginHints' => $this->loginHints->forStudent($student),
            'hasPayments' => $isStudent && $actor->can('reports.view') && $this->studentPaymentsQuery($student, $partnerId)->exists(),
            'hasContracts' => $isStudent && $actor->can('contracts.view') && $this->studentContractsQuery($student, $partnerId)->exists(),
            'canViewEmails' => true,
            'hasEmails' => $this->studentEmailsQuery($student, $partnerId)->exists(),
            'siblings' => $isStudent ? $this->siblings($student, $partnerId) : [],
            'canViewPayments' => $isStudent && $actor->can('reports.view'),
            'canViewContracts' => $isStudent && $actor->can('contracts.view'),
            'canLinkTeams' => $actor->can('groups.view'),
            'can' => [
                'name' => $actor->can('users.name.update'),
                'birthday' => $actor->can('users.birthdate.update'),
                'phone' => $actor->can('users.phone.update'),
                'email' => $actor->can('users.email.update'),
                'activity' => $actor->can('users.activity.update'),
                'sex' => $isStudent && $actor->can('users.sex'),
                'comment' => $actor->can('users.comment'),
                'health' => $isStudent && $actor->can('users.other.update'),
                'discount' => $isStudent && $actor->can('users.discount.manage'),
                'groups' => $isStudent && $actor->can('users.group.update'),
            ],
            'health' => [
                'is_individual_traits' => $this->healthFormValue($student, 'is_individual_traits'),
                'is_on_medical_register' => $this->healthFormValue($student, 'is_on_medical_register'),
                'is_with_disability' => $this->healthFormValue($student, 'is_with_disability'),
            ],
            'discountPercent' => UserPercentDiscount::percent($student),
            'discountComment' => (string) (UserPercentDiscount::comment($student) ?? ''),
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function paymentsData(User $user): JsonResponse
    {
        $student = $this->studentOrAbort($user);
        $partnerId = $this->requirePartnerId();
        $teamUserSync = app(TeamUserSyncService::class);

        $query = $this->studentPaymentsQuery($student, $partnerId)
            ->with([
                'user.teams' => fn ($q) => $q->where('teams.partner_id', $partnerId),
                'paidTeam:id,title,partner_id',
            ])
            ->leftJoin('payment_intents as pi_card', function ($join) {
                $join->on('payments.partner_id', '=', 'pi_card.partner_id')
                    ->where('pi_card.provider', '=', 'tbank')
                    ->whereRaw(
                        'pi_card.provider_inv_id = CAST(NULLIF(NULLIF(TRIM(payments.payment_number), ""), "0") AS UNSIGNED)'
                    );
            })
            ->select(
                'payments.*',
                'pi_card.payment_method_webhook as intent_payment_method_webhook',
                'pi_card.payment_method as intent_payment_method_init',
            );

        return DataTables::of($query)
            ->addColumn('paid_at', function (Payment $row) {
                $raw = $row->operation_date;
                if ($raw === null || $raw === '') {
                    return '—';
                }

                return Carbon::parse($raw)->format('d.m.Y H:i');
            })
            ->addColumn('summ', function (Payment $row) {
                $rubles = ((int) $row->summ_cents) / 100;

                return number_format($rubles, 2, ',', ' ').' руб';
            })
            ->addColumn('status_label', function (Payment $row) {
                return $this->paymentStatusLabel($row->payment_status);
            })
            ->addColumn('team_title', function (Payment $row) use ($teamUserSync) {
                return PaymentTeamTitleDisplay::forRow($row, $teamUserSync);
            })
            ->addColumn('type_label', function (Payment $row) {
                return $this->paymentTypeLabel($row->payment_month);
            })
            ->addColumn('method_label', function (Payment $row) {
                return $this->paymentMethodLabel($row);
            })
            ->orderColumn('paid_at', function ($query, $order) {
                $query->orderBy('payments.operation_date', $order);
            })
            ->orderColumn('summ', function ($query, $order) {
                $query->orderBy('payments.summ_cents', $order);
            })
            ->orderColumn('status_label', function ($query, $order) {
                $query->orderBy('payments.payment_status', $order);
            })
            ->orderColumn('team_title', function ($query, $order) {
                $query->orderBy('payments.team_title', $order);
            })
            ->orderColumn('type_label', function ($query, $order) {
                $query->orderBy('payments.payment_month', $order);
            })
            ->orderColumn('method_label', function ($query, $order) {
                $query->orderBy('pi_card.payment_method_webhook', $order);
            })
            ->make(true);
    }

    public function contractsData(User $user): JsonResponse
    {
        $student = $this->studentOrAbort($user);
        $partnerId = $this->requirePartnerId();

        $query = $this->studentContractsQuery($student, $partnerId)->select('contracts.*');

        return DataTables::of($query)
            ->addColumn('number', fn (Contract $contract) => (string) $contract->id)
            ->addColumn('status_label', fn (Contract $contract) => $contract->status_ru)
            ->addColumn('created_label', function (Contract $contract) {
                return $contract->created_at
                    ? $contract->created_at->format('d.m.Y H:i')
                    : '—';
            })
            ->addColumn('signed_label', function (Contract $contract) {
                return $contract->signed_at
                    ? $contract->signed_at->format('d.m.Y H:i')
                    : '—';
            })
            ->addColumn('url', fn (Contract $contract) => route('contracts.show', $contract))
            ->orderColumn('number', function ($query, $order) {
                $query->orderBy('contracts.id', $order);
            })
            ->orderColumn('status_label', function ($query, $order) {
                $query->orderBy('contracts.status', $order);
            })
            ->orderColumn('created_label', function ($query, $order) {
                $query->orderBy('contracts.created_at', $order);
            })
            ->orderColumn('signed_label', function ($query, $order) {
                $query->orderBy('contracts.signed_at', $order);
            })
            ->make(true);
    }

    public function emailsData(User $user): JsonResponse
    {
        $student = $this->cardUserOrAbort($user);
        $partnerId = $this->requirePartnerId();

        $query = $this->studentEmailsQuery($student, $partnerId)
            ->select([
                'id',
                'sent_at',
                'created_at',
                'status',
                'to_summary',
                'subject',
                'send_attempts',
                'error_message',
            ]);

        return DataTables::of($query)
            ->addColumn('sent_label', function (OutgoingEmailLog $row) {
                $moment = $row->sent_at ?? $row->created_at;

                return $moment ? $moment->timezone((string) config('app.timezone'))->format('d.m.Y H:i') : '—';
            })
            ->addColumn('error_excerpt', function (OutgoingEmailLog $row) {
                $error = trim((string) ($row->error_message ?? ''));

                return $error === '' ? '' : Str::limit($error, 200, '…');
            })
            ->addColumn('show_url', fn (OutgoingEmailLog $row) => route('admin.user.email-show', ['user' => $student->id, 'log' => $row->id]))
            ->orderColumn('sent_label', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw('COALESCE(outgoing_email_logs.sent_at, outgoing_email_logs.created_at) '.$dir);
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderBy('outgoing_email_logs.status', $order);
            })
            ->orderColumn('to_summary', function ($query, $order) {
                $query->orderBy('outgoing_email_logs.to_summary', $order);
            })
            ->orderColumn('subject', function ($query, $order) {
                $query->orderBy('outgoing_email_logs.subject', $order);
            })
            ->orderColumn('send_attempts', function ($query, $order) {
                $query->orderBy('outgoing_email_logs.send_attempts', $order);
            })
            ->make(true);
    }

    public function emailShow(Request $request, User $user, OutgoingEmailLog $log)
    {
        $student = $this->cardUserOrAbort($user);
        $partnerId = $this->requirePartnerId();
        $belongs = $this->studentEmailsQuery($student, $partnerId)
            ->where('outgoing_email_logs.id', $log->id)
            ->exists();
        if (! $belongs) {
            abort(404);
        }

        return view('admin.report.partials.outgoing_email_show_content', [
            'log' => $log,
            'inModal' => true,
        ]);
    }

    public function logsData(UserCardLogsRequest $request, User $user): JsonResponse
    {
        $student = $this->cardUserOrAbort($user);
        $partnerId = $this->requirePartnerId();

        $logs = MyLog::query()
            ->with(['author'])
            ->where('my_logs.partner_id', $partnerId)
            ->where(function ($q) use ($student) {
                $q->where('my_logs.user_id', $student->id)
                    ->orWhere(function ($target) use ($student) {
                        $target->where('my_logs.target_id', $student->id)
                            ->where('my_logs.target_type', $student->getMorphClass());
                    });
            })
            ->when($request->hideAuthorizations(), function ($q) {
                AuditLogQueryScopes::applyHideAuthorizations($q);
            })
            ->select('my_logs.*');

        return DataTables::of($logs)
            ->addColumn('author', fn (MyLog $log) => $log->author?->full_name ?: '—')
            ->editColumn('action', fn (MyLog $log) => $log->eventLabel())
            ->editColumn('description', function (MyLog $log) {
                $text = trim((string) ($log->description ?? ''));
                if ($text === '') {
                    return '—';
                }

                return nl2br(e($text));
            })
            ->editColumn('created_at', function (MyLog $log) {
                return $log->created_at
                    ? $log->created_at->format('d.m.Y / H:i:s')
                    : '—';
            })
            ->rawColumns(['description'])
            ->make(true);
    }

    private function studentPaymentsQuery(User $student, int $partnerId)
    {
        return Payment::query()
            ->where('payments.user_id', $student->id)
            ->where(function ($query) use ($partnerId) {
                $query->where('payments.partner_id', $partnerId)
                    ->orWhereNull('payments.partner_id');
            });
    }

    private function studentEmailsQuery(User $student, int $partnerId)
    {
        $email = mb_strtolower(trim((string) $student->email));
        $query = OutgoingEmailLog::query()->where('outgoing_email_logs.partner_id', $partnerId);
        if ($email === '') {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($inner) use ($email) {
            foreach (['to_addresses', 'cc_addresses', 'bcc_addresses'] as $column) {
                $inner->orWhereJsonContains('outgoing_email_logs.'.$column, ['address' => $email]);
            }
            $inner->orWhereRaw('LOWER(outgoing_email_logs.to_summary) LIKE ?', ['%'.addcslashes($email, '%_\\').'%']);
        });
    }

    private function presenceSeenLabel(User $student): string
    {
        if ($student->isOnline() || $student->last_seen_at === null) {
            return '';
        }

        return $student->last_seen_at
            ->timezone((string) config('app.timezone'))
            ->format('d.m.Y H:i');
    }

    private function studentContractsQuery(User $student, int $partnerId)
    {
        return Contract::query()
            ->where('contracts.school_id', $partnerId)
            ->where('contracts.user_id', $student->id);
    }

    private function cardUserOrAbort(User $user): User
    {
        $person = $this->access->findCardUser($user);
        if ($person === null) {
            abort(404);
        }

        return $person;
    }

    private function studentOrAbort(User $user): User
    {
        $student = $this->access->findStudent($user);
        if ($student === null) {
            abort(404);
        }

        return $student;
    }

    /**
     * @return array{name: string, avatar: string, status_label: string, is_enabled: bool, birthday: string, age: string}
     */
    private function header(User $student): array
    {
        $birthday = $student->birthday;

        return [
            'name' => $student->full_name !== ''
                ? $student->full_name
                : 'Без имени',
            'avatar' => $student->image_crop
                ? asset('storage/avatars/'.$student->image_crop)
                : asset('img/default-avatar.png'),
            'status_label' => $student->is_enabled ? 'Активен' : 'Неактивен',
            'is_enabled' => (bool) $student->is_enabled,
            'is_online' => $student->isOnline(),
            'presence_label' => $student->isOnline() ? 'Онлайн' : 'Офлайн',
            'presence_seen' => $this->presenceSeenLabel($student),
            'birthday' => $birthday ? $birthday->format('d.m.Y') : '',
            'age' => $this->ageLabel($birthday),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function infoRows(User $student, User $actor): array
    {
        $rows = [
            ['label' => 'Фамилия', 'value' => $this->text($student->lastname)],
            ['label' => 'Имя', 'value' => $this->text($student->name)],
            ['label' => 'Отчество', 'value' => $this->text($student->middlename)],
            ['label' => 'Дата рождения', 'value' => $student->birthday ? $student->birthday->format('d.m.Y') : '—'],
        ];

        if ($actor->can('users.sex')) {
            $rows[] = ['label' => 'Пол', 'value' => UserSex::labelFor($student->sex)];
        }

        $rows[] = ['label' => 'Телефон', 'value' => $this->text(RuPhone::formatForInput($student->phone))];
        $rows[] = ['label' => 'Email', 'value' => $this->text($student->email)];
        $rows[] = ['label' => 'Статус', 'value' => $student->is_enabled ? 'Активен' : 'Неактивен'];

        if ($actor->can('users.comment')) {
            $rows[] = ['label' => 'Комментарий', 'value' => $this->text($student->comment)];
        }

        $rows[] = ['label' => 'Адрес проживания', 'value' => $this->text($student->address)];
        $rows[] = ['label' => 'Инд. особенности (физические, психологические)', 'value' => $this->triState($this->rawNullableBool($student, 'is_individual_traits'))];
        $rows[] = ['label' => 'Состоит на учёте у медицинских специалистов', 'value' => $this->triState($this->rawNullableBool($student, 'is_on_medical_register'))];
        $rows[] = ['label' => 'Наличие инвалидности', 'value' => $this->triState($this->rawNullableBool($student, 'is_with_disability'))];

        if ($actor->can('users.discount.manage')) {
            $percent = UserPercentDiscount::percent($student);
            $rows[] = [
                'label' => 'Скидка',
                'value' => $percent >= 1 ? $percent.'%' : 'Нет',
            ];
            $rows[] = [
                'label' => 'Комментарий к скидке',
                'value' => $percent >= 1 ? $this->text(UserPercentDiscount::comment($student)) : '—',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{title: string, location: string, trainers: string}>
     */
    private function groups(User $student, User $actor, int $partnerId): array
    {
        $teams = $student->teams()
            ->where('teams.partner_id', $partnerId)
            ->wherePivot('partner_id', $partnerId)
            ->with([
                'location:id,name',
                'trainerProfiles.user:id,name,lastname',
            ])
            ->get();

        $allowed = $this->ownTeams->allowedTeamIds($actor, $partnerId);
        if ($allowed !== null) {
            $teams = $teams->whereIn('id', $allowed)->values();
        }

        return $teams->map(function ($team) {
            $trainers = $team->trainerProfiles
                ->map(function ($profile) {
                    $user = $profile->user;
                    if ($user === null) {
                        return '';
                    }

                    return trim($user->full_name) !== '' ? $user->full_name : trim((string) $user->name);
                })
                ->filter(fn (string $name) => $name !== '')
                ->unique()
                ->values()
                ->implode(', ');

            return [
                'id' => (int) $team->id,
                'title' => (string) $team->title,
                'location' => $this->text($team->location?->name),
                'trainers' => $trainers !== '' ? $trainers : '—',
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function teamOptions(User $actor, int $partnerId): array
    {
        $query = Team::query()
            ->where('partner_id', $partnerId)
            ->orderBy('order_by')
            ->orderBy('title');
        $this->ownTeams->restrictTeamsQuery($query, $actor, $partnerId);

        return $query->get(['id', 'title'])->map(fn (Team $team) => [
            'id' => (int) $team->id,
            'title' => (string) $team->title,
        ])->all();
    }

    /**
     * @return list<int>
     */
    private function selectedTeamIds(User $student, User $actor, int $partnerId): array
    {
        return array_map(
            'intval',
            array_column($this->groups($student, $actor, $partnerId), 'id'),
        );
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function customFields(User $student, User $actor, int $partnerId): array
    {
        $fieldsQuery = UserField::query()
            ->with('roles')
            ->where('partner_id', $partnerId)
            ->orderBy('name');

        if (! $this->isSuperAdmin($actor) && $actor->role_id) {
            $fieldsQuery->whereHas('roles', fn ($q) => $q->where('role_id', $actor->role_id));
        }

        $values = UserFieldValue::query()
            ->where('user_id', $student->id)
            ->pluck('value', 'field_id');

        return $fieldsQuery->get()->map(function (UserField $field) use ($values) {
            return [
                'label' => (string) $field->name,
                'slug' => (string) $field->slug,
                'field_type' => (string) $field->field_type,
                'value' => $this->text($values->get($field->id)),
                'raw' => trim((string) ($values->get($field->id) ?? '')),
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function parentRows(User $student): array
    {
        $fields = $student->parentFormFields();

        $fio = trim(implode(' ', array_filter([
            $fields['parent_lastname'] ?? null,
            $fields['parent_firstname'] ?? null,
            $fields['parent_middlename'] ?? null,
        ], fn ($part) => trim((string) $part) !== '')));

        return [
            ['label' => 'ФИО', 'value' => $this->text($fio)],
            ['label' => 'ФИО в родительном падеже', 'value' => $this->text($fields['parent_full_name_genitive'] ?? null)],
            ['label' => 'Телефон', 'value' => $this->text(RuPhone::formatForInput($fields['parent_phone'] ?? null))],
            ['label' => 'Email', 'value' => $this->text($fields['parent_email'] ?? null)],
            ['label' => 'Паспорт', 'value' => $this->text($fields['parent_passport'] ?? null)],
            ['label' => 'Кем и когда выдан', 'value' => $this->text($fields['parent_passport_issued'] ?? null)],
            ['label' => 'Адрес', 'value' => $this->text($fields['parent_address'] ?? null)],
        ];
    }

    /**
     * @return list<array{id: int, name: string, url: string, teams: string, status_label: string, is_enabled: bool}>
     */
    private function siblings(User $student, int $partnerId): array
    {
        $parentId = (int) ($student->parent_id ?? 0);
        if ($parentId < 1) {
            return [];
        }

        $siblings = User::query()
            ->with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->wherePivot('partner_id', $partnerId)])
            ->where('partner_id', $partnerId)
            ->where('parent_id', $parentId)
            ->whereKeyNot($student->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'user'))
            ->orderBy('lastname')
            ->orderBy('name')
            ->get();

        return $siblings->map(function (User $sibling) use ($partnerId) {
            $name = trim($sibling->fullNameWithPatronymic());
            if ($name === '') {
                $name = 'Без имени';
            }

            $teams = app(TrainerOwnTeamsScope::class)
                ->visibleTeamTitlesLabel($sibling, Auth::user(), $partnerId);

            return [
                'id' => (int) $sibling->id,
                'name' => $name,
                'url' => route('admin.user.show', $sibling),
                'teams' => $teams !== '' ? $teams : '—',
                'status_label' => $sibling->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled' => (bool) $sibling->is_enabled,
            ];
        })->all();
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '—';
    }

    private function healthFormValue(User $student, string $column): string
    {
        $value = $this->rawNullableBool($student, $column);
        if ($value === null) {
            return '';
        }

        return $value ? '1' : '0';
    }

    private function rawNullableBool(User $student, string $column): ?bool
    {
        $raw = $student->getAttributes()[$column] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (bool) $raw;
    }

    private function triState(?bool $value): string
    {
        if ($value === null) {
            return 'Не указано';
        }

        return $value ? 'Да' : 'Нет';
    }

    private function ageLabel(?Carbon $birthday): string
    {
        if ($birthday === null) {
            return '';
        }

        $years = $birthday->age;
        $mod100 = $years % 100;
        $mod10 = $years % 10;
        $word = ($mod100 > 10 && $mod100 < 20)
            ? 'лет'
            : match ($mod10) {
                1 => 'год',
                2, 3, 4 => 'года',
                default => 'лет',
            };

        return $years.' '.$word;
    }

    private function paymentStatusLabel(mixed $status): string
    {
        $raw = trim((string) ($status ?? ''));
        if ($raw === '') {
            return '—';
        }

        return match (strtoupper($raw)) {
            'CONFIRMED', 'PAID', 'SUCCEEDED', 'SUCCESS' => 'Оплачен',
            'AUTHORIZED' => 'Авторизован',
            'NEW', 'PENDING', 'FORM_SHOWED', 'AUTHORIZING', '3DS_CHECKING', '3DS_CHECKED' => 'В обработке',
            'REJECTED', 'AUTH_FAIL' => 'Отклонён',
            'CANCELED', 'CANCELLED' => 'Отменён',
            'REFUNDED' => 'Возврат',
            'PARTIAL_REFUNDED' => 'Частичный возврат',
            'DEADLINE_EXPIRED' => 'Истёк срок',
            'FAILED' => 'Ошибка',
            default => $raw,
        };
    }

    private function paymentTypeLabel(mixed $paymentMonth): string
    {
        $value = trim((string) ($paymentMonth ?? ''));
        if ($value === '') {
            return '—';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return 'Ежемесячный платеж';
        }

        return $value;
    }

    private function paymentMethodLabel(Payment $row): string
    {
        $code = (string) ($row->intent_payment_method_webhook ?? $row->intent_payment_method_init ?? '');
        if ($code !== '') {
            return match ($code) {
                'card' => 'Карта',
                'sbp_qr' => 'QR (СБП)',
                'tpay' => 'T-Pay',
                default => $code,
            };
        }

        if (! empty($row->deal_id) || ! empty($row->payment_id) || ! empty($row->payment_status)) {
            return 'T-Bank';
        }

        return 'Robokassa';
    }
}
