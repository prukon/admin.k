<!-- Модальное окно для создания группы -->
<!-- Модальное окно для заявки -->
<div class="modal fade" id="createOrder" tabindex="-1" aria-labelledby="createOrderLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createOrderLabel">Оставить заявку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">


                <form id="contactForm" class="text-start" action="{{ route('contact.send') }}" method="post">
                    @csrf

                    <input type="hidden" name="form_started_at" id="form_started_at" value="{{ now()->toIso8601String() }}">

                    <!-- honeypot: скрытое поле, должно остаться пустым -->
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="website_hp">Ваш сайт (не заполняйте это поле)</label>
                        <input type="text" name="website_hp" id="website_hp" autocomplete="off" tabindex="-1">
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" id="name" value="{{ old('name') }}">
                        @error('name') <p class="text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" id="phone" value="{{ old('phone') }}">
                        @error('phone') <p class="text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email (необязательно)</label>
                        <input type="email" name="email" class="form-control" id="email" value="{{ old('email') }}">
                        @error('email') <p class="text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{--<div class="mb-3">--}}
                        {{--<label for="website" class="form-label">Сайт (необязательно)</label>--}}
                        {{--<input type="url" name="website" class="form-control" id="website" placeholder="https://example.com" value="{{ old('website') }}">--}}
                        {{--@error('website') <p class="text-danger">{{ $message }}</p> @enderror--}}
                    {{--</div>--}}

                    <div class="mb-3">
                        <label for="website" class="form-label">Сайт (необязательно)</label>
                        <input type="text" name="website" class="form-control" id="website"
                               placeholder="example.com или https://example.com"
                               inputmode="url" autocomplete="url"
                               pattern="^(https?:\/\/)?([\w.-]+\.[a-z]{2,})(\/.*)?$">
                        @error('website') <p class="text-danger">{{ $message }}</p> @enderror
                    </div>


                    <div class="mb-3">
                        <label for="message" class="form-label">Сообщение (необязательно)</label>
                        <textarea name="message" class="form-control" id="message">{{ old('message') }}</textarea>
                        @error('message') <p class="text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="modal-footer-create-team">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary" id="sendBtn">Отправить</button>
                    </div>
                </form>


            </div>
        </div>
    </div>
</div>
