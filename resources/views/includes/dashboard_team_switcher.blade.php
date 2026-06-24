@cannot('users.view')
    @if(isset($curUser) && $curUser->teams->count() >= 2)
        <div class="row dashboard-team-switcher mt-3 mb-2">
            <div class="col-12 col-md-4 text-start">
                <label for="dashboard-active-team" class="form-label mb-1">Выбор группы</label>
                <select id="dashboard-active-team" class="form-select">
                    @foreach($curUser->teams as $team)
                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif
@endcannot
