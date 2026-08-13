{{-- Стили иконки % у поля цены (не зависит от Vite-сборки). --}}
<style>
    .kids-user-discount-price-wrap {
        position: relative;
    }
    .kids-user-discount-badge {
        position: absolute;
        top: 0;
        right: 0.2rem;
        z-index: 2;
        line-height: 1;
    }
    .kids-user-discount-badge .kids-tooltip-hint {
        font-size: 0.7rem;
        color: #0d6efd;
        cursor: help;
        padding: 0.1rem;
    }
    .kids-user-discount-badge.is-hidden {
        display: none !important;
    }
</style>
