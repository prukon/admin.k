<style>
    .contract-memo-timeline {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        gap: 0.35rem 0;
    }

    .contract-memo-timeline__step {
        flex: 1 1 88px;
        min-width: 88px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 0.5rem;
        padding: 0.75rem 0.5rem;
        background: #f8f9fa;
        color: #6c757d;
        text-align: center;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .contract-memo-timeline__step--done {
        background: #d1e7dd;
        border-color: #a3cfbb;
        color: #0f5132;
    }

    .contract-memo-timeline__step--active {
        background: #cfe2ff;
        border-color: #9ec5fe;
        color: #084298;
    }

    .contract-memo-timeline__step--failed {
        background: #f8d7da;
        border-color: #f1aeb5;
        color: #842029;
    }

    .contract-memo-timeline__num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 50%;
        font-size: 0.8rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        background: rgba(255, 255, 255, 0.65);
    }

    .contract-memo-timeline__label {
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.25;
        margin-bottom: 0.25rem;
    }

    .contract-memo-timeline__time {
        font-size: 0.75rem;
        font-weight: 500;
        opacity: 0.95;
    }

    .contract-memo-timeline__hint {
        font-size: 0.7rem;
        margin-top: 0.25rem;
        opacity: 0.85;
        line-height: 1.35;
        white-space: pre-line;
        text-align: left;
    }

    .contract-memo-timeline__arrow {
        flex: 0 0 auto;
        align-self: center;
        color: #adb5bd;
        font-size: 1.25rem;
        line-height: 1;
        padding: 0 0.15rem;
        user-select: none;
    }

    @media (max-width: 767.98px) {
        .contract-memo-timeline__arrow {
            display: none;
        }

        .contract-memo-timeline__step {
            flex: 1 1 100%;
        }
    }

    #contractStatusMemoModal .modal-dialog,
    #contractPathModal .modal-dialog {
        max-width: min(1100px, 96vw);
    }

    #contractStatusMemoModal .modal-body,
    #contractPathModal .modal-body {
        max-height: calc(100vh - 11rem);
        overflow-y: auto;
    }
</style>
