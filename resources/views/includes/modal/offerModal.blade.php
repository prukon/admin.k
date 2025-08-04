<div class="modal fade" id="partnerOfferModal" tabindex="-1" role="dialog" aria-labelledby="offerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Партнёрская оферта</h5>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
{{--                @include('admin.partnerOferta')--}}
                @include('partner-offer-multirasschety')
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('partner.accept-offer') }}">
                    @csrf
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" name="confirm" id="confirm" required>
                        <label class="form-check-label" for="confirm">
                            Я ознакомлен с условиями оферты и принимаю их
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Принять условия</button>
                </form>
            </div>
        </div>
    </div>
</div>