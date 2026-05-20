@php
    $teamsFieldIdPrefix = $teamsFieldIdPrefix ?? 'trainer-teams';
@endphp
<div class="mb-3">
    <label class="form-label">{{ $teamsLabel ?? 'Группы' }}</label>
    <div class="js-trainer-teams-checkboxes border rounded p-2 bg-light" style="max-height: 180px; overflow-y: auto;">
        @forelse($teamOptions as $team)
            <div class="form-check mb-1">
                <input class="form-check-input"
                       type="checkbox"
                       name="team_ids[]"
                       value="{{ $team->id }}"
                       id="{{ $teamsFieldIdPrefix }}-team-{{ $team->id }}">
                <label class="form-check-label" for="{{ $teamsFieldIdPrefix }}-team-{{ $team->id }}">
                    {{ $team->title }}
                </label>
            </div>
        @empty
            <div class="text-muted small mb-0">Нет доступных групп</div>
        @endforelse
    </div>
    <div class="form-text">Отметьте группы, которые ведёт тренер. На одну группу — один тренер.</div>
    <div class="invalid-feedback d-block" data-error-for="team_ids"></div>
</div>
