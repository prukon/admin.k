@php
    use App\Enums\UserSex;

    $fieldPrefix = match ($prefix ?? 'edit') {
        'create' => 'create-',
        default  => 'edit-',
    };
    $canSex = (bool) ($canViewUserSex ?? (auth()->user()?->can('users.sex') ?? false));
    $canComment = (bool) ($canViewUserComment ?? (auth()->user()?->can('users.comment') ?? false));
@endphp

@if ($canSex || $canComment)
    <div class="col-12 js-user-comment-sex-wrap {{ ($prefix ?? 'edit') === 'create' ? '' : 'd-none' }}" data-comment-sex-prefix="{{ $prefix ?? 'edit' }}">
        <div class="row g-3">
            @if ($canSex)
                <div class="col-12 col-md-6 js-user-comment-sex-sex-field">
                    <label for="{{ $fieldPrefix }}sex" class="form-label">Пол</label>
                    <select id="{{ $fieldPrefix }}sex" name="sex" class="form-select js-user-comment-sex-field">
                        <option value="">Не указано</option>
                        @foreach (UserSex::cases() as $sexCase)
                            <option value="{{ $sexCase->value }}">{{ $sexCase->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($canComment)
                <div class="col-12 {{ $canSex ? 'col-md-6' : '' }} js-user-comment-sex-comment-field">
                    <label for="{{ $fieldPrefix }}comment" class="form-label">Комментарий</label>
                    <textarea id="{{ $fieldPrefix }}comment"
                              name="comment"
                              class="form-control js-user-comment-sex-field"
                              rows="3"
                              maxlength="5000"></textarea>
                </div>
            @endif
        </div>
    </div>
@endif
