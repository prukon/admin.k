{{-- Карандаш: открыть модалку attach группы (право + eligible-контекст). --}}
@can('account.user.team.update')
    @if(!empty($cabinetTeamAttach) && is_array($cabinetTeamAttach))
        <a href="#cabinetAttachTeamModal"
           class="cabinet-attach-team-pencil ms-1"
           data-bs-toggle="modal"
           data-bs-target="#cabinetAttachTeamModal"
           aria-label="Добавить группу"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
    @endif
@endcan
