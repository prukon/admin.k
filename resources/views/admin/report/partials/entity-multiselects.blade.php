@php
    $filters = $filters ?? [];
    $filterTeams = $filterTeams ?? collect();
    $filterTrainers = $filterTrainers ?? collect();
    $activeLocations = $activeLocations ?? collect();
    $canViewTrainers = $canViewTrainers ?? false;
    $canViewLocations = $canViewLocations ?? false;
    $entityFilterSelected = function (string $key) use ($filters): array {
        $raw = $filters[$key] ?? null;
        $items = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw]);
        $out = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    };
    $entityTeamIds = $entityFilterSelected('filter_team_id');
    $entityTrainerIds = $entityFilterSelected('filter_trainer_profile_id');
    $entityLocationIds = $entityFilterSelected('filter_location_id');
@endphp
<div class="col-12 col-md-3 generic-multiselect-field">
    <label class="form-label" for="{{ $teamFieldId }}">Группа</label>
    <select class="form-select js-generic-multiselect-select"
            id="{{ $teamFieldId }}"
            name="filter_team_id[]"
            multiple
            data-placeholder="Все группы">
        @foreach($filterTeams as $team)
            <option value="{{ $team->id }}" @selected(in_array((string) $team->id, $entityTeamIds, true))>{{ $team->title }}</option>
        @endforeach
    </select>
</div>
@if($canViewTrainers)
<div class="col-12 col-md-3 generic-multiselect-field">
    <label class="form-label" for="{{ $trainerFieldId }}">Тренер</label>
    <select class="form-select js-generic-multiselect-select"
            id="{{ $trainerFieldId }}"
            name="filter_trainer_profile_id[]"
            multiple
            data-placeholder="Все тренеры">
        @foreach($filterTrainers as $trainer)
            <option value="{{ $trainer->id }}" @selected(in_array((string) $trainer->id, $entityTrainerIds, true))>{{ $trainer->user?->full_name }}</option>
        @endforeach
    </select>
</div>
@endif
@if($canViewLocations)
<div class="col-12 col-md-3 generic-multiselect-field">
    <label class="form-label" for="{{ $locationFieldId }}">Объект</label>
    <select class="form-select js-generic-multiselect-select"
            id="{{ $locationFieldId }}"
            name="filter_location_id[]"
            multiple
            data-placeholder="Все объекты">
        <option value="none" @selected(in_array('none', $entityLocationIds, true))>Без объекта</option>
        @foreach($activeLocations as $location)
            <option value="{{ $location->id }}" @selected(in_array((string) $location->id, $entityLocationIds, true))>{{ $location->name }}</option>
        @endforeach
    </select>
</div>
@endif
