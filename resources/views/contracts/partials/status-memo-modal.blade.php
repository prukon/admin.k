@php
    $contractPathTimelineBuilder = app(\App\Services\Contracts\ContractPathTimelineBuilder::class);
    $contractMemoTemplateSteps = $contractPathTimelineBuilder->schematicTemplateSteps();
    $contractMemoOtherSteps = $contractPathTimelineBuilder->schematicOtherSteps();
@endphp

@include('contracts.partials.status-memo-timeline-styles')

<div class="modal fade"
     id="contractStatusMemoModal"
     tabindex="-1"
     aria-labelledby="contractStatusMemoModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contractStatusMemoModalLabel">Памятка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body text-start">
                <h6 class="mb-3">Путь с формой клиенту</h6>
                @include('contracts.partials.status-memo-timeline', [
                    'steps' => $contractMemoTemplateSteps,
                    'ariaLabel' => 'Путь с формой клиенту',
                ])

                <h6 class="mb-3 mt-4">Другие статусы</h6>
                @include('contracts.partials.status-memo-timeline', [
                    'steps' => $contractMemoOtherSteps,
                    'ariaLabel' => 'Другие статусы договора',
                    'showArrows' => false,
                ])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
