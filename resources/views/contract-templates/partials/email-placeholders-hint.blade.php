<div class="form-text">
    Кнопка «По умолчанию» вставляет системный текст с ссылкой
    <code>href="&#123;&#123;documents_url&#125;&#125;"</code>.
    При отправке письма токен становится
    <code>/account-settings/documents?student=…&amp;fill=…</code>
    (нужный ребёнок и модалка заполнения).
    Не собирайте URL из кусков и не подставляйте обычный
    <code>/account-settings/documents</code> без query.
    Другие плейсхолдеры (удобно править в режиме «Код» <i class="fas fa-code"></i>):
    <code>&#123;&#123;child_full_name&#125;&#125;</code>,
    <code>&#123;&#123;partner_name&#125;&#125;</code>,
    <code>&#123;&#123;fill_deadline&#125;&#125;</code>,
    <code>&#123;&#123;contract_id&#125;&#125;</code>
    (номер в тексте, не query).
</div>
