@php
    $users = $group['users'];
    $journalOccurrences = $group['occurrences'];
    $journalAssignments = $group['assignments'];
    $journalPaymentStatuses = $group['payments'];
    $journalConsumingCounts = $group['consuming'];
    $postpayByUser = $group['postpayByUser'];
    $postpayUsers = $group['postpayUsers'];
    $postpayLockedUsers = $group['postpayLocked'];
    $flexibleByUser = $group['flexibleByUser'];
    $flexibleUsers = $group['flexibleUsers'];
    $groupWeekdays = $group['weekdays'];
    $groupTeamId = $group['team_id'];
    $groupKey = $group['key'];
    $journalGroupCollapsed = ! empty($journalGroupCollapsed);
    $groupPageUrl = function (int $page) use ($groupKey) {
        $query = request()->except(['page', 'group_key', 'group_page']);
        $pages = (array) ($query['group_pages'] ?? []);
        $pages[$groupKey] = $page;
        $query['group_pages'] = $pages;

        return route('schedule.index', $query);
    };
@endphp
@foreach($users as $index => $user)
    @php
        $studentTeamIds = $groupTeamId ? [$groupTeamId] : [];
        $journalContextTeamId = $groupTeamId;
                        $userAssignments = $journalAssignments[(int) $user->id] ?? [];
                        $settingPricesPlaceable = collect($userAssignments)->filter(
                            static fn ($a) => ! empty($a['placeable'])
                                && ! empty($a['from_setting_prices'])
                                && (int) ($a['team_id'] ?? 0) > 0
                        )->values();
                        $hasPlaceable = $settingPricesPlaceable->isNotEmpty();
                        $placeableHoverLines = [];
                        foreach ($settingPricesPlaceable as $assignment) {
                            $placeableHoverLines[] = \App\Services\Schedule\ScheduleJournalMonthService::fixedAbonementPlaceButtonHoverLine(
                                (string) ($assignment['name'] ?? 'Абонемент'),
                                (int) ($assignment['lessons_remaining'] ?? 0),
                                (int) ($assignment['lessons_total'] ?? 0),
                                (int) ($assignment['fee_amount_cents'] ?? 0),
                            );
                        }
                        $placeableHoverText = implode("\n", $placeableHoverLines);
                        $userFlexibleAssignments = $flexibleByUser[(int) $user->id] ?? [];
                        $hasFlexibleAssignable = !empty($flexibleUsers[(int) $user->id]) && $userFlexibleAssignments !== [];
                        $flexibleHintCount = count($userFlexibleAssignments);
                        // Фильтр группы сужает список до одного; без фильтра при нескольких — иконка.
                        $flexibleHintShowRatio = $flexibleHintCount === 1;
                        $flexibleHintText = '';
                        $flexibleHintRatio = '';
                        if ($hasFlexibleAssignable && $flexibleHintCount === 1) {
                            $fa = $userFlexibleAssignments[0];
                            $flexName = (string) ($fa['name'] ?? 'Абонемент предоплаты');
                            $flexRem = (int) ($fa['slots_remaining'] ?? 0);
                            $flexTotal = (int) ($fa['lessons_total'] ?? 0);
                            $flexFee = (int) ($fa['fee_amount_cents'] ?? 0);
                            $flexibleHintRatio = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnLabel(
                                $flexRem,
                                $flexTotal
                            );
                            $flexibleHintText = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
                                $flexName,
                                $flexFee
                            );
                        } elseif ($hasFlexibleAssignable && $flexibleHintCount > 1) {
                            $flexLines = [];
                            foreach ($userFlexibleAssignments as $fa) {
                                $flexLines[] = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
                                    (string) ($fa['name'] ?? 'Абонемент предоплаты'),
                                    (int) ($fa['fee_amount_cents'] ?? 0),
                                    true,
                                    (int) ($fa['slots_remaining'] ?? 0),
                                    (int) ($fa['lessons_total'] ?? 0),
                                );
                            }
                            $flexibleHintText = implode("\n", $flexLines);
                        }
                        $userPostpayHints = $postpayByUser[(int) $user->id] ?? [];
                        $postpayHintLabels = [];
                        $postpayHintHovers = [];
                        foreach ($userPostpayHints as $ph) {
                            $postpayHintLabels[] = (string) ($ph['label'] ?? '');
                            $postpayHintHovers[] = (string) ($ph['hover'] ?? ($ph['label'] ?? ''));
                        }
                        $postpayHintLabels = array_values(array_filter($postpayHintLabels, static fn ($v) => $v !== ''));
                        $postpayHintText = implode("\n", $postpayHintLabels);
                        $postpayHintHover = implode("\n", array_values(array_filter($postpayHintHovers, static fn ($v) => $v !== '')));
                        $consumingCount = (int) ($journalConsumingCounts[(int) $user->id] ?? 0);
                    @endphp
    <tr class="schedule-group-user{{ $journalGroupCollapsed ? ' schedule-group-collapsed' : '' }}" data-group-key="{{ $groupKey }}" data-user-id="{{ $user->id }}" @if($groupTeamId) data-team-id="{{ $groupTeamId }}" @endif>
        <td class="text-center align-middle sticky-col-1 number-line">{{ ($users->firstItem() ?? 1) + $index }}</td>
        <td class="schedule-user-name sticky-col-2 align-middle">
            <button type="button" class="schedule-user-card-name js-user-card" data-user-id="{{ $user->id }}">{{ $user?->full_name ?: 'Без имени' }}</button>
        </td>
                        <td class="text-center align-middle schedule-payment-status">
                            @php
                                $payStatus = $journalPaymentStatuses[(int) $user->id] ?? null;
                                $payState = is_array($payStatus) ? (string) ($payStatus['state'] ?? '') : '';
                                $payHover = is_array($payStatus) ? (string) ($payStatus['hover'] ?? '') : '';
                                $payIcon = is_array($payStatus) ? (string) ($payStatus['icon_class'] ?? '') : '';
                                $payAmountLabel = is_array($payStatus) ? (string) ($payStatus['amount_label'] ?? '') : '';
                            @endphp
                            @if($payState === 'paid' || $payState === 'partial')
                                <span data-journal-payment-status="{{ $payState }}">
                                    @if($payHover !== '')
                                        @include('partials.ui.tooltip-hint', [
                                            'title' => $payHover,
                                            'placement' => 'top',
                                            'iconClass' => $payIcon,
                                            'wrapperClass' => 'journal-monthly-payment-hint',
                                            'container' => 'body',
                                        ])
                                    @else
                                        <i class="{{ $payIcon }}" aria-hidden="true"></i>
                                    @endif
                                </span>
                            @elseif($payState === 'due' && $payAmountLabel !== '')
                                <span data-journal-payment-status="due">
                                    @if($payHover !== '')
                                        @include('partials.ui.tooltip-hint', [
                                            'title' => $payHover,
                                            'placement' => 'top',
                                            'innerHtml' => e($payAmountLabel),
                                            'wrapperClass' => 'journal-monthly-payment-hint journal-monthly-payment-due',
                                            'container' => 'body',
                                        ])
                                    @else
                                        <span class="journal-monthly-payment-due">{{ $payAmountLabel }}</span>
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="text-center align-middle schedule-consuming-count"
                            data-journal-consuming-count="{{ $consumingCount }}"
                            data-order="{{ $consumingCount }}">
                            @if($consumingCount > 0)
                                {{ $consumingCount }}
                            @endif
                        </td>
                        <td class="text-center align-middle schedule-col-setup schedule-col-abonements">
                            <div class="journal-abonement-cell d-inline-flex align-items-center gap-1 justify-content-center flex-wrap">
                                @if($hasPlaceable)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary journal-abonement-btn kids-tooltip-hint"
                                            data-user-id="{{ $user->id }}"
                                            data-kids-tooltip-hint="1"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="ulp-assignment-paid-tooltip"
                                            data-bs-container="body"
                                            title="{{ $placeableHoverText }}"
                                            aria-label="{{ $placeableHoverText }}">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                @endif
                                @if($hasFlexibleAssignable && $flexibleHintText !== '')
                                    @if($flexibleHintShowRatio)
                                        @php $fa = $userFlexibleAssignments[0]; @endphp
                                        <span class="kids-tooltip-hint text-muted journal-flexible-hint journal-flexible-hint--ratio"
                                              tabindex="0"
                                              role="img"
                                              aria-label="{{ $flexibleHintText }}"
                                              data-kids-tooltip-hint="1"
                                              data-bs-toggle="tooltip"
                                              data-bs-placement="top"
                                              data-bs-custom-class="ulp-assignment-paid-tooltip"
                                              data-bs-container="body"
                                              data-flexible-ulp-id="{{ (int) ($fa['id'] ?? 0) }}"
                                              data-slots-remaining="{{ (int) ($fa['slots_remaining'] ?? 0) }}"
                                              data-lessons-total="{{ (int) ($fa['lessons_total'] ?? 0) }}"
                                              data-fee-amount-cents="{{ (int) ($fa['fee_amount_cents'] ?? 0) }}"
                                              data-package-name="{{ $fa['name'] ?? 'Абонемент предоплаты' }}"
                                              data-starts-at="{{ $fa['starts_at'] ?? '' }}"
                                              data-ends-at="{{ $fa['ends_at'] ?? '' }}"
                                              title="{{ $flexibleHintText }}">{{ $flexibleHintRatio }}</span>
                                    @else
                                        <i class="fa-solid fa-circle-info text-muted journal-flexible-hint journal-flexible-hint--multi"
                                           tabindex="0"
                                           role="img"
                                           aria-label="{{ $flexibleHintText }}"
                                           data-kids-tooltip-hint="1"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           data-bs-custom-class="ulp-assignment-paid-tooltip"
                                           data-bs-container="body"
                                           data-flexible-items="{{ e(json_encode(array_map(static fn ($fa) => [
                                               'id' => (int) ($fa['id'] ?? 0),
                                               'name' => (string) ($fa['name'] ?? 'Абонемент предоплаты'),
                                               'slots_remaining' => (int) ($fa['slots_remaining'] ?? 0),
                                               'lessons_total' => (int) ($fa['lessons_total'] ?? 0),
                                               'fee_amount_cents' => (int) ($fa['fee_amount_cents'] ?? 0),
                                               'starts_at' => (string) ($fa['starts_at'] ?? ''),
                                               'ends_at' => (string) ($fa['ends_at'] ?? ''),
                                           ], $userFlexibleAssignments), JSON_UNESCAPED_UNICODE)) }}"
                                           title="{{ $flexibleHintText }}"></i>
                                    @endif
                                @endif
                                @if($postpayHintText !== '')
                                    <span class="kids-tooltip-hint text-muted journal-postpay-hint"
                                          tabindex="0"
                                          role="img"
                                          aria-label="{{ $postpayHintHover !== '' ? $postpayHintHover : $postpayHintText }}"
                                          data-kids-tooltip-hint="1"
                                          data-bs-toggle="tooltip"
                                          data-bs-placement="top"
                                          data-bs-custom-class="ulp-assignment-paid-tooltip"
                                          data-bs-container="body"
                                          data-price-cents="{{ (int) ($userPostpayHints[0]['price_cents'] ?? 0) }}"
                                          title="{{ $postpayHintHover !== '' ? $postpayHintHover : $postpayHintText }}">{{ $postpayHintText }}</span>
                                @endif
                            </div>
                        </td>

                        @foreach($days as $day)
                            @php
                                $dateKey = $user->id . '_' . $day->format('Y-m-d');
                                $dayItems = $journalOccurrences[$dateKey] ?? [];
                                $count = count($dayItems);
                                $primary = $count === 1 ? $dayItems[0] : null;
                                // Без статуса (типично после привязки в календаре школы) ячейка всё равно должна быть видна.
                                $cellColor = $primary['status_color'] ?? ($count > 0 ? '#e9ecef' : '');
                                $cellIcon = $primary['status_icon'] ?? '';
                                $cellTitle = $primary['status_title'] ?? '';
                                $hasStatusVisual = ($cellIcon !== '' && $cellIcon !== null) || ($cellTitle !== '' && $cellTitle !== null);
                                $isPostpayUser = !empty($postpayUsers[(int) $user->id]);
                                $isPostpayLocked = !empty($postpayLockedUsers[(int) $user->id]);
                                $isFlexibleUser = !empty($flexibleUsers[(int) $user->id]);
                                $flexibleRemainingTotal = 0;
                                foreach ($userFlexibleAssignments as $faRow) {
                                    $flexibleRemainingTotal += max(0, (int) ($faRow['slots_remaining'] ?? 0));
                                }
                                $flexibleHasRemaining = $isFlexibleUser && $flexibleRemainingTotal > 0;
                                $flexibleAtLimit = $isFlexibleUser && $flexibleRemainingTotal < 1;
                                // Прямой гибкий: есть остаток, либо лимит без права на пробное/разовое.
                                $canOpenEmptyFlexible = $groupTeamId && $count === 0 && (
                                    $flexibleHasRemaining
                                    || ($flexibleAtLimit && empty($canPlaceEmptyCellLesson))
                                );
                                // Постоплата важнее chooser'а при лимите гибкого.
                                $canOpenEmptyPostpay = $groupTeamId && $isPostpayUser && $count === 0 && !$isPostpayLocked && !$flexibleHasRemaining;
                                // Пробное/разовое (+ гибкий в выборе при лимите).
                                $canOpenEmptyLesson = $groupTeamId && !empty($canPlaceEmptyCellLesson)
                                    && $count === 0
                                    && !$canOpenEmptyPostpay
                                    && (!$isFlexibleUser || $flexibleAtLimit);
                                $cellClickable = $count > 0 || $canOpenEmptyPostpay || $canOpenEmptyFlexible || $canOpenEmptyLesson;
                                $cellPackageHover = '';
                                if ($count === 1) {
                                    $cellPackageHover = (string) ($primary['package_hover'] ?? $primary['package_name'] ?? '');
                                } elseif ($count > 1) {
                                    $hoverLines = [];
                                    foreach ($dayItems as $dayItem) {
                                        $line = trim((string) ($dayItem['package_hover'] ?? $dayItem['package_name'] ?? ''));
                                        if ($line !== '') {
                                            $hoverLines[] = $line;
                                        }
                                    }
                                    $cellPackageHover = implode("\n", $hoverLines);
                                }
                                $bulkBlock = 'no_abonement';
                                $bulkBilling = '';
                                if ($count > 0) {
                                    $bulkBlock = 'occupied';
                                } elseif ($flexibleHasRemaining) {
                                    $bulkBlock = 'eligible';
                                    $bulkBilling = 'prepaid';
                                } elseif ($canOpenEmptyPostpay) {
                                    $bulkBlock = 'eligible';
                                    $bulkBilling = 'postpay';
                                } elseif ($isFlexibleUser && ! $flexibleHasRemaining) {
                                    $bulkBlock = 'prepaid_empty';
                                } elseif ($isPostpayLocked) {
                                    $bulkBlock = 'postpay_paid';
                                } elseif ($hasPlaceable) {
                                    $bulkBlock = 'fixed_only';
                                }
                            @endphp
                            <td class="schedule-cell text-center position-relative
                                @if(count($groupWeekdays) && in_array($day->format('N'), $groupWeekdays)) highlight-column @endif"
                                data-user-id="{{ $user->id }}"
                                data-user-name="{{ $user?->full_name ?: 'Без имени' }}"
                                data-context-team-id="{{ $journalContextTeamId ?? '' }}"
                                data-team-ids="{{ implode(',', $studentTeamIds) }}"
                                data-date="{{ $day->format('Y-m-d') }}"
                                data-occurrence-count="{{ $count }}"
                                data-postpay="{{ $isPostpayUser ? '1' : '0' }}"
                                data-postpay-locked="{{ $isPostpayLocked ? '1' : '0' }}"
                                data-flexible="{{ $isFlexibleUser ? '1' : '0' }}"
                                data-flexible-remaining="{{ $isFlexibleUser ? (int) $flexibleRemainingTotal : 0 }}"
                                data-empty-lesson="{{ $canOpenEmptyLesson ? '1' : '0' }}"
                                data-bulk-block="{{ $bulkBlock }}"
                                data-bulk-billing="{{ $bulkBilling }}"
                                data-bulk-fixed="{{ $hasPlaceable ? '1' : '0' }}"
                                @if($cellPackageHover !== '')
                                    data-package-hover="{{ $cellPackageHover }}"
                                    data-kids-tooltip-hint="1"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="ulp-assignment-paid-tooltip"
                                    data-bs-container="body"
                                    title="{{ $cellPackageHover }}"
                                @elseif($isPostpayLocked && $bulkBlock !== 'eligible' && $bulkBlock !== 'prepaid_empty')
                                    data-kids-tooltip-hint="1"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="ulp-assignment-paid-tooltip"
                                    data-bs-container="body"
                                    title="Изменить данные нельзя, поскольку уже была произведена оплата"
                                @endif
                                @if($primary)
                                    data-utss-id="{{ $primary['utss_id'] }}"
                                    data-status-id="{{ $primary['lesson_occurrence_status_id'] }}"
                                    data-comment="{{ $primary['comment'] }}"
                                @endif
                                style="cursor: {{ $cellClickable || $isPostpayLocked ? 'pointer' : 'default' }};">
                                @if($count > 1)
                                    <span class="schedule-cell__swatch" @if($cellColor) style="background-color: {{ $cellColor }};" @endif>
                                        <span class="badge bg-primary">×{{ $count }}</span>
                                    </span>
                                @elseif($count === 1)
                                    <span class="schedule-cell__swatch" @if($cellColor) style="background-color: {{ $cellColor }};" @endif>
                                        @if($hasStatusVisual)
                                            @if($cellIcon)
                                                <i class="{{ $cellIcon }} schedule-cell-status-icon" aria-hidden="true"></i>
                                            @else
                                                {{ $cellTitle }}
                                            @endif
                                        @else
                                            <i class="fa-solid fa-circle text-secondary schedule-cell-empty-dot" aria-hidden="true"></i>
                                        @endif
                                    </span>
                                    @if(!empty($primary['comment']))
                                        <div class="cell-comment-indicator"
                                             style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>
                                    @endif
                                @elseif($canOpenEmptyFlexible)
                                    <i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" title="Абонемент предоплаты: поставить занятие"></i>
                                @elseif($canOpenEmptyPostpay)
                                    <i class="fa-regular fa-circle text-muted schedule-cell-empty-dot" style="opacity: 0.45;" title="Постоплата: отметить посещение"></i>
                                @elseif($canOpenEmptyLesson)
                                    <i class="fa-regular fa-circle text-secondary schedule-cell-empty-dot" style="opacity: 0.35;"
                                       title="{{ $flexibleAtLimit ? 'Пробное, разовое или занятие из абонемента предоплаты' : 'Пробное или разовое занятие' }}"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

@if($users->total() >= \App\Services\Schedule\ScheduleJournalPageLength::DEFAULT)
    @php
        $groupPageCurrent = $users->currentPage();
        $groupPageLast = $users->lastPage();
        $groupPageFrom = max(1, $groupPageCurrent - 1);
        $groupPageTo = min($groupPageLast, $groupPageCurrent + 1);
        $journalGroupPerPage = (int) ($journalGroupPerPage ?? \App\Services\Schedule\ScheduleJournalPageLength::forUser(auth()->user()));
    @endphp
    <tr class="schedule-group-pager{{ $journalGroupCollapsed ? ' schedule-group-collapsed' : '' }}" data-group-key="{{ $groupKey }}">
        <td class="schedule-group-pager-cell" colspan="{{ 5 + count($days) }}">
            <div class="schedule-journal-pagination">
                <div class="schedule-journal-pagination__meta"><span class="schedule-journal-pagination__range">{{ $users->firstItem() }}–{{ $users->lastItem() }}</span> <span class="schedule-journal-pagination__of">из {{ $users->total() }}</span></div>
                <div class="schedule-journal-per-page">
                    <div class="schedule-journal-per-page__row">
                        <span class="schedule-journal-per-page__label">Показывать по</span>
                        <select class="schedule-journal-per-page__select" aria-label="Показывать по" data-field="page_length">
                            @foreach(\App\Services\Schedule\ScheduleJournalPageLength::LENGTHS as $journalPerPageOption)
                                <option value="{{ $journalPerPageOption }}" @selected($journalPerPageOption === $journalGroupPerPage)>{{ $journalPerPageOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="schedule-journal-per-page__error text-danger" data-error-for="page_length" hidden></div>
                </div>
                @if($users->lastPage() > 1)
                <div class="schedule-group-pages">
                    @if($groupPageCurrent > 1)
                        <a href="{{ $groupPageUrl($groupPageCurrent - 1) }}" class="schedule-group-page-link" data-group-key="{{ $groupKey }}" data-page="{{ $groupPageCurrent - 1 }}">‹</a>
                    @endif
                    @for($groupPage = $groupPageFrom; $groupPage <= $groupPageTo; $groupPage++)
                        @if($groupPage === $groupPageCurrent)
                            <span class="schedule-group-page-current">{{ $groupPage }}</span>
                        @else
                            <a href="{{ $groupPageUrl($groupPage) }}" class="schedule-group-page-link" data-group-key="{{ $groupKey }}" data-page="{{ $groupPage }}">{{ $groupPage }}</a>
                        @endif
                    @endfor
                    @if($groupPageCurrent < $groupPageLast)
                        <a href="{{ $groupPageUrl($groupPageCurrent + 1) }}" class="schedule-group-page-link" data-group-key="{{ $groupKey }}" data-page="{{ $groupPageCurrent + 1 }}">›</a>
                    @endif
                </div>
                @endif
            </div>
        </td>
    </tr>
@endif
