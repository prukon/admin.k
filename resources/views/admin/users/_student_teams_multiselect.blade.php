@php
    $teamsFieldId = $teamsFieldId ?? 'studentTeamIds';
    $teamsLabel = $teamsLabel ?? 'Группы';
    $canEditTeams = $canEditTeams ?? auth()->user()?->can('users.group.update');
    $teamOptions = $teamOptions ?? collect();
@endphp

@if($teamOptions->isNotEmpty())
<div class="mb-3 generic-multiselect-field js-user-student-teams-field">
    <label class="form-label" for="{{ $teamsFieldId }}">{{ $teamsLabel }}</label>
    <select id="{{ $teamsFieldId }}"
            name="team_ids[]"
            class="form-select js-generic-multiselect-select js-user-student-teams-select"
            multiple
            data-placeholder="Выберите группы"
            @unless($canEditTeams) disabled aria-disabled="true" @endunless>
        @foreach($teamOptions as $team)
            <option value="{{ $team->id }}">{{ $team->title }}</option>
        @endforeach
    </select>
    <div class="invalid-feedback d-block" data-error-for="team_ids"></div>
    @unless($canEditTeams)
        <div class="form-text text-muted mt-1">
            <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение групп
        </div>
    @endunless
</div>
@endif
