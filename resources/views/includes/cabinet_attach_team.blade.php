{{-- Группы активного ученика + ссылка «изменить» (модалка attach). --}}
@can('account.user.team.update')
    @if(!empty($cabinetTeamAttach) && is_array($cabinetTeamAttach))
        @php
            $cabinetTeamsLabel = (string) ($cabinetTeamAttach['current_teams_label'] ?? '');
        @endphp
        <div class="cabinet-attach-team-trigger px-0 pb-1" style="max-width: 210px;">
            <h6 class="d-flex align-items-baseline gap-1 min-w-0 mb-1">
                <span class="flex-shrink-0">Группа:</span>
                <span class="dt-cell-ellipsis js-dt-cell-ellipsis-tooltip min-w-0"
                      data-dt-ellipsis-title="{{ e($cabinetTeamsLabel) }}"
                      tabindex="0"
                      aria-label="{{ e($cabinetTeamsLabel) }}">{{ $cabinetTeamsLabel }}</span>
                <a href="#cabinetAttachTeamModal"
                   class="flex-shrink-0"
                   data-bs-toggle="modal"
                   data-bs-target="#cabinetAttachTeamModal">изменить</a>
            </h6>
        </div>
        @once
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(function () {
                        var root = document.querySelector('.cabinet-attach-team-trigger');
                        if (root && window.KidsCrmTooltip) {
                            window.KidsCrmTooltip.init(root, { scopes: ['text'] });
                        }
                    });
                });
            </script>
        @endonce
    @endif
@endcan
