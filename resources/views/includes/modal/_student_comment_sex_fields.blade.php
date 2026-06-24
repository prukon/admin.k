@php
    use App\Enums\UserSex;

    $fieldPrefix = match ($prefix ?? 'edit') {
        'create' => 'create-',
        default  => 'edit-',
    };
    $canSex = (bool) ($canViewUserSex ?? (auth()->user()?->can('users.sex') ?? false));
    $canComment = (bool) ($canViewUserComment ?? (auth()->user()?->can('users.comment') ?? false));
    $only = $only ?? 'both';
    $showSex = $canSex && in_array($only, ['sex', 'both'], true);
    $showComment = $canComment && in_array($only, ['comment', 'both'], true);
    $studentOnlyHidden = ($prefix ?? 'edit') === 'create' ? '' : 'd-none';
@endphp

@if ($showSex && $only === 'sex')
    <div class="col-12 col-md-6 js-user-sex-wrap {{ $studentOnlyHidden }}" data-comment-sex-prefix="{{ $prefix ?? 'edit' }}">
        <label for="{{ $fieldPrefix }}sex" class="form-label">Пол</label>
        <select id="{{ $fieldPrefix }}sex" name="sex" class="form-select js-user-comment-sex-field">
            <option value="">Не указано</option>
            @foreach (UserSex::cases() as $sexCase)
                <option value="{{ $sexCase->value }}">{{ $sexCase->label() }}</option>
            @endforeach
        </select>
    </div>
@endif

@if ($showComment && $only === 'comment')
    <div class="col-12 js-user-comment-wrap {{ $studentOnlyHidden }}" data-comment-sex-prefix="{{ $prefix ?? 'edit' }}">
        <div class="mb-3">
            <label for="{{ $fieldPrefix }}comment" class="form-label">Комментарий</label>
            <textarea id="{{ $fieldPrefix }}comment"
                      name="comment"
                      class="form-control js-user-comment-sex-field"
                      rows="3"
                      maxlength="5000"></textarea>
        </div>
    </div>
@endif

@if ($showSex || $showComment)
    @if ($only === 'both')
        <div class="col-12 js-user-comment-sex-wrap {{ $studentOnlyHidden }}" data-comment-sex-prefix="{{ $prefix ?? 'edit' }}">
            <div class="row g-3">
                @if ($showSex)
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

                @if ($showComment)
                    <div class="col-12 {{ $showSex ? 'col-md-6' : '' }} js-user-comment-sex-comment-field">
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
@endif
