@php
    $formatMoney = static function ($value): string {
        return number_format((float) $value, 2, '.', ' ');
    };
@endphp

@if(count($latestByTrainer ?? []) > 0)
    <div class="trainer-salary-sheets-latest mt-4">
        <h2 class="h6 fw-semibold mb-2">Актуальная версия по каждому тренеру</h2>
        <p class="text-muted small mb-3">Последний слепок за выбранный месяц (независимо от способа формирования).</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Тренер</th>
                    <th>Версия</th>
                    <th>Дата</th>
                    <th class="text-end">Итого</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($latestByTrainer as $row)
                    <tr>
                        <td>{{ $row['trainer_name'] }}</td>
                        <td>v{{ (int) $row['version'] }}</td>
                        <td class="text-nowrap">{{ $row['formed_at_display'] }}</td>
                        <td class="text-end text-nowrap">{{ $formatMoney($row['grand_total']) }}</td>
                        <td class="text-end">
                            <a href="{{ $row['show_url'] }}" class="btn btn-sm btn-link">Открыть</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
