<?php

declare(strict_types=1);

namespace App\Services\LessonPackages\Export;

use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Выгрузка XLS: занятия календаря школы + сводка назначений абонементов.
 */
final class LessonPackagesSchoolScheduleExportBuilder
{
    public function __construct(
        private readonly LessonOccurrenceHistoricalRemainingResolver $historicalRemainingResolver,
    ) {}

    /**
     * @return StreamedResponse
     */
    public function download(int $partnerId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): StreamedResponse
    {
        $fromYmd = $dateFrom->format('Y-m-d');
        $toYmd = $dateTo->format('Y-m-d');

        $spreadsheet = new Spreadsheet();

        $lessonsSheet = $spreadsheet->getActiveSheet();
        $lessonsSheet->setTitle('Занятия');
        $this->fillLessonsSheet($lessonsSheet, $partnerId, $fromYmd, $toYmd);

        $assignmentsSheet = $spreadsheet->createSheet();
        $assignmentsSheet->setTitle('Назначения');
        $this->fillAssignmentsSheet($assignmentsSheet, $partnerId, $fromYmd, $toYmd);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf('lesson-packages-export_%s_%s.xlsx', $fromYmd, $toYmd);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillLessonsSheet(Worksheet $sheet, int $partnerId, string $fromYmd, string $toYmd): void
    {
        $headers = [
            'Дата',
            'Время начала',
            'Время окончания',
            'Ученик',
            'Телефон ученика',
            'ФИО родителя',
            'Телефон родителя',
            'Группа',
            'Тип записи',
            'Абонемент',
            '№ назначения',
            'Стоимость',
            'Оплата',
            'Статус',
            'Списано',
            'Источник статуса',
            'Остаток',
            'Всего',
        ];
        $this->writeHeaderRow($sheet, $headers);

        $rows = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->whereDate('starts_at', '>=', $fromYmd)
            ->whereDate('starts_at', '<=', $toYmd)
            ->with([
                'user:id,name,lastname,phone,parent_id',
                'user.parentProfile:id,lastname,firstname,middlename,phone',
                'slot:id,team_id,time_start,time_end',
                'slot.team:id,title',
                'userLessonPackage:id,fee_amount,is_paid,is_manual_paid,lessons_remaining,lessons_total,lesson_package_id,team_id',
                'userLessonPackage.lessonPackage:id,name,schedule_type',
                'userLessonPackage.team:id,title',
            ])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $latestEvents = $this->latestStatusEventsByKey($partnerId, $fromYmd, $toYmd);
        $historicalRemaining = $this->historicalRemainingResolver->resolveForExportRows($partnerId, $rows);

        $excelRow = 2;
        foreach ($rows as $utss) {
            $dateYmd = $utss->starts_at instanceof \Carbon\CarbonInterface
                ? $utss->starts_at->format('Y-m-d')
                : (string) $utss->starts_at;

            $isTrial = (bool) $utss->is_trial_lesson;
            $ulp = $utss->userLessonPackage;
            $lp = $ulp?->lessonPackage;
            $ulpId = $utss->user_lesson_package_id !== null ? (int) $utss->user_lesson_package_id : 0;
            $eventKey = (int) $utss->user_id.'|'.(int) $utss->team_schedule_slot_id.'|'.$dateYmd.'|'.$ulpId;
            /** @var UserLessonOccurrenceStatusEvent|null $event */
            $event = $latestEvents[$eventKey] ?? null;
            $status = $event?->lessonOccurrenceStatus;

            $teamTitle = $utss->slot?->team?->title
                ?? $ulp?->team?->title
                ?? '';

            $typeLabel = $this->registrationTypeLabel($isTrial, $lp?->schedule_type);
            $packageName = $isTrial
                ? 'Пробное занятие'
                : trim((string) ($lp?->name ?? ''));
            if (! $isTrial && $packageName === '') {
                $packageName = $ulp !== null ? 'Абонемент' : '';
            }

            $fee = null;
            $paymentLabel = '';
            $remaining = $historicalRemaining[$eventKey] ?? null;
            $total = null;
            $assignmentNo = '';

            if ($isTrial) {
                $total = (int) ($utss->trial_lessons_total ?? 1);
                $paymentLabel = '—';
                if ($remaining === null) {
                    $remaining = $total;
                }
            } elseif ($ulp !== null) {
                $assignmentNo = (string) (int) $ulp->id;
                $fee = $ulp->fee_amount;
                $paymentLabel = $this->paymentLabel($ulp);
                $total = (int) $ulp->lessons_total;
                if ($remaining === null) {
                    $remaining = (int) $ulp->lessons_total;
                }
            }

            $consumed = $status !== null && (bool) $status->consumes_lesson;

            $contact = $this->studentParentContactFields($utss->user instanceof User ? $utss->user : null);

            $this->writeDataRow($sheet, $excelRow, [
                $dateYmd,
                $this->formatTime($utss->slot?->time_start),
                $this->formatTime($utss->slot?->time_end),
                $this->userLabel($utss->user, (int) $utss->user_id),
                $contact['student_phone'],
                $contact['parent_full_name'],
                $contact['parent_phone'],
                (string) $teamTitle,
                $typeLabel,
                $packageName,
                $assignmentNo,
                $fee !== null ? $this->formatMoney($fee) : '',
                $paymentLabel,
                $status !== null ? (string) $status->title : '',
                $status === null ? '' : ($consumed ? 'да' : 'нет'),
                $this->statusSourceLabel($event),
                $remaining !== null ? (string) $remaining : '',
                $total !== null ? (string) $total : '',
            ]);
            $excelRow++;
        }

        $this->autosizeColumns($sheet, count($headers));
    }

    private function fillAssignmentsSheet(Worksheet $sheet, int $partnerId, string $fromYmd, string $toYmd): void
    {
        $headers = [
            'Ученик',
            'Телефон ученика',
            'ФИО родителя',
            'Телефон родителя',
            'Группа',
            'Абонемент',
            'Тип',
            '№ назначения',
            'Период с',
            'Период по',
            'Стоимость',
            'Оплата',
            'Всего',
            'Остаток',
            'Списано',
            'Привязок в календаре',
        ];
        $this->writeHeaderRow($sheet, $headers);

        $assignments = UserLessonPackage::query()
            ->whereHas('lessonPackage', static function ($q) use ($partnerId): void {
                $q->where('partner_id', $partnerId);
            })
            ->where(function ($q) use ($fromYmd, $toYmd): void {
                // Без периода — всегда в выгрузке (ещё не привязаны к календарю).
                $q->where(function ($inner): void {
                    $inner->whereNull('starts_at')->whereNull('ends_at');
                })->orWhere(function ($inner) use ($fromYmd, $toYmd): void {
                    // Пересечение периода назначения с выбранным диапазоном.
                    $inner->whereNotNull('starts_at')
                        ->where('starts_at', '<=', $toYmd)
                        ->where(function ($ends) use ($fromYmd): void {
                            $ends->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', $fromYmd);
                        });
                });
            })
            ->with([
                'user:id,name,lastname,phone,parent_id',
                'user.parentProfile:id,lastname,firstname,middlename,phone',
                'team:id,title',
                'lessonPackage:id,name,schedule_type,partner_id',
            ])
            ->withCount('userTeamScheduleSlots')
            ->orderBy('id')
            ->get();

        $excelRow = 2;
        foreach ($assignments as $ulp) {
            $lp = $ulp->lessonPackage;
            $total = (int) $ulp->lessons_total;
            $remaining = (int) $ulp->lessons_remaining;
            $consumed = max(0, $total - $remaining);

            $starts = $ulp->starts_at instanceof \Carbon\CarbonInterface
                ? $ulp->starts_at->format('Y-m-d')
                : ($ulp->starts_at !== null ? (string) $ulp->starts_at : '');
            $ends = $ulp->ends_at instanceof \Carbon\CarbonInterface
                ? $ulp->ends_at->format('Y-m-d')
                : ($ulp->ends_at !== null ? (string) $ulp->ends_at : '');

            $contact = $this->studentParentContactFields($ulp->user instanceof User ? $ulp->user : null);

            $this->writeDataRow($sheet, $excelRow, [
                $this->userLabel($ulp->user, (int) $ulp->user_id),
                $contact['student_phone'],
                $contact['parent_full_name'],
                $contact['parent_phone'],
                (string) ($ulp->team?->title ?? ''),
                trim((string) ($lp?->name ?? 'Абонемент')),
                $this->scheduleTypeLabel($lp?->schedule_type),
                (string) (int) $ulp->id,
                $starts !== '' ? $starts : 'не задан',
                $ends !== '' ? $ends : 'не задан',
                $this->formatMoney($ulp->fee_amount),
                $this->paymentLabel($ulp),
                (string) $total,
                (string) $remaining,
                (string) $consumed,
                (string) (int) $ulp->user_team_schedule_slots_count,
            ]);
            $excelRow++;
        }

        $this->autosizeColumns($sheet, count($headers));
    }

    /**
     * Последнее событие статуса по ключу user|slot|date|ulpId (0 для пробных).
     *
     * @return array<string, UserLessonOccurrenceStatusEvent>
     */
    private function latestStatusEventsByKey(int $partnerId, string $fromYmd, string $toYmd): array
    {
        $events = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->whereDate('occurrence_date', '>=', $fromYmd)
            ->whereDate('occurrence_date', '<=', $toYmd)
            ->with(['lessonOccurrenceStatus:id,title,consumes_lesson'])
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'team_schedule_slot_id',
                'occurrence_date',
                'user_lesson_package_id',
                'lesson_occurrence_status_id',
                'created_by',
            ]);

        $latest = [];
        foreach ($events as $event) {
            $dateStr = $event->occurrence_date instanceof \Carbon\CarbonInterface
                ? $event->occurrence_date->format('Y-m-d')
                : (string) $event->occurrence_date;
            $ulpKey = (int) ($event->user_lesson_package_id ?? 0);
            $key = (int) $event->user_id.'|'.(int) $event->team_schedule_slot_id.'|'.$dateStr.'|'.$ulpKey;
            $latest[$key] = $event;
        }

        return $latest;
    }

    private function registrationTypeLabel(bool $isTrial, ?string $scheduleType): string
    {
        if ($isTrial) {
            return 'Пробное';
        }

        return $this->scheduleTypeLabel($scheduleType);
    }

    private function scheduleTypeLabel(?string $scheduleType): string
    {
        return match ((string) $scheduleType) {
            'fixed' => 'Фиксированный',
            'flexible' => 'Гибкий',
            'no_schedule' => 'Разовое',
            default => $scheduleType !== null && $scheduleType !== '' ? (string) $scheduleType : '',
        };
    }

    private function paymentLabel(UserLessonPackage $ulp): string
    {
        $paid = (bool) $ulp->effective_is_paid;
        $label = $paid ? 'Оплачен' : 'Не оплачен';

        if ($ulp->is_manual_paid !== null) {
            $label .= ' (ручная отметка)';
        }

        return $label;
    }

    private function statusSourceLabel(?UserLessonOccurrenceStatusEvent $event): string
    {
        if ($event === null) {
            return '';
        }

        return $event->created_by === null ? 'авто' : 'вручную';
    }

    private function userLabel(?object $user, int $userId): string
    {
        if ($user === null) {
            return 'Ученик #'.$userId;
        }

        $label = trim((string) (($user->lastname ?? '').' '.($user->name ?? '')));

        return $label !== '' ? $label : 'Ученик #'.$userId;
    }

    /**
     * Контакты ученика и родителя для колонок выгрузки.
     *
     * @return array{student_phone: string, parent_full_name: string, parent_phone: string}
     */
    private function studentParentContactFields(?User $user): array
    {
        if ($user === null) {
            return [
                'student_phone' => '',
                'parent_full_name' => '',
                'parent_phone' => '',
            ];
        }

        $user->loadMissing('parentProfile');
        $profile = $user->parentProfile;

        return [
            'student_phone' => trim((string) ($user->phone ?? '')),
            'parent_full_name' => $profile !== null ? trim((string) $profile->full_name) : '',
            'parent_phone' => $profile !== null ? trim((string) ($profile->phone ?? '')) : '',
        ];
    }

    private function formatTime(mixed $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        $raw = (string) $time;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $raw;
    }

    private function formatMoney(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param list<string> $headers
     */
    private function writeHeaderRow(Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
    }

    /**
     * @param list<string|int|float|null> $values
     */
    private function writeDataRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $col = $index + 1;
            // Деньги / телефоны E.164 (+…) — как текст, иначе Excel съедает формат.
            if (is_string($value) && (
                preg_match('/^\d+\.\d{2}$/', $value) === 1
                || str_starts_with($value, '+')
            )) {
                $sheet->setCellValueExplicit([$col, $row], $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue([$col, $row], $value);
            }
        }
    }

    private function autosizeColumns(Worksheet $sheet, int $columnCount): void
    {
        for ($col = 1; $col <= $columnCount; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
    }
}
