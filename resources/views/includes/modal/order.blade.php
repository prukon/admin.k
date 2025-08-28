
<!-- Модалка заявки -->
<div class="modal fade" id="createOrder" tabindex="-1" aria-labelledby="createOrderLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="createOrderLabel">Оставить заявку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>

            <div class="modal-body">
                <form id="contactForm" class="text-start" action="/contact/send" method="post" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" name="name" id="name" class="form-control" value="">
                        <div class="invalid-feedback d-block" data-error-for="name" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="">
                        <div class="invalid-feedback d-block" data-error-for="phone" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email (необязательно)</label>
                        <input type="text" name="email" id="email" class="form-control" value="">
                        <div class="invalid-feedback d-block" data-error-for="email" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="website" class="form-label">Сайт (необязательно)</label>
                        <input type="text" name="website" id="website" class="form-control" placeholder="example.com или https://example.com">
                        <div class="invalid-feedback d-block" data-error-for="website" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Сообщение (необязательно)</label>
                        <textarea name="message" id="message" class="form-control" rows="3"></textarea>
                        <div class="invalid-feedback d-block" data-error-for="message" style="display:none;"></div>
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

<!-- jQuery AJAX -->
<script>
    $(function () {
        $(document).on('submit', '#contactForm', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $btn  = $('#sendBtn');

            // 1) Сброс старых ошибок
            $('[data-error-for]').hide().text('');

            // 2) Нормализуем website (добавим https:// если пользователь ввёл голый домен)
            var $w = $('#website');
            var wv = $.trim($w.val());
            if (wv && !/^https?:\/\//i.test(wv)) {
                $w.val('https://' + wv);
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: '/contact/send', // ПРЯМОЙ URL
                method: 'POST',
                data: $form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]', $form).val(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                dataType: 'json',
                success: function (resp) {
                    // Успех — твоя функция
                    $form[0].reset();
                    showSuccessModal('Отправка заявки', resp.message || 'Заявка отправлена.', 1);
                    // По желанию можно закрывать модалку:
                    // $('#createOrder').modal('hide');
                },
                error: function (xhr) {
                    var res = xhr.responseJSON || {};
                    var errors = res.errors || {};
                    // Выводим ошибки под полями
                    Object.keys(errors).forEach(function (name) {
                        var msg = errors[name][0];
                        var $placeholder = $('[data-error-for="'+name+'"]');
                        if ($placeholder.length) {
                            $placeholder.text(msg).show();
                        }
                    });
                    // Если Laravel вернул общее сообщение — покажем под первым полем
                    if (!Object.keys(errors).length && res.message) {
                        var $any = $('[data-error-for]').first();
                        $any.text(res.message).show();
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
</script>
ц