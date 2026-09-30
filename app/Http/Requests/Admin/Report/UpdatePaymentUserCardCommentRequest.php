<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePaymentUserCardCommentRequest extends FormRequest
{
    private ?User $student = null;

    private bool $studentMissing = false;

    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null || ! $actor->can('users.comment')) {
            return false;
        }

        $routeUser = $this->route('user');
        if (! is_numeric($routeUser)) {
            $this->studentMissing = true;

            return false;
        }

        $student = User::withTrashed()->find((int) $routeUser);
        if (! $student instanceof User) {
            $this->studentMissing = true;

            return false;
        }

        $partnerId = app(PartnerContext::class)->partnerId();
        if (! $partnerId || (int) $student->partner_id !== (int) $partnerId) {
            return false;
        }

        $this->student = $student;

        return true;
    }

    public function student(): User
    {
        if (! $this->student instanceof User) {
            throw new \RuntimeException('Комментарий пользователя не разрешён.');
        }

        return $this->student;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->can('users.comment')) {
            $this->offsetUnset('comment');

            return;
        }

        $comment = trim((string) $this->input('comment', ''));
        $this->merge([
            'comment' => $comment !== '' ? $comment : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'comment' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'comment' => 'Комментарий',
            'user' => 'пользователь',
        ];
    }

    public function messages(): array
    {
        return [
            'comment.present' => 'Укажите комментарий.',
            'comment.string' => 'Поле «Комментарий» должно быть строкой.',
            'comment.max' => 'Поле «Комментарий» не должно превышать :max символов.',
            'user.unauthorized' => 'Нет доступа к комментарию этого пользователя.',
            'user.missing' => 'Пользователь не найден.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first() ?: 'Проверьте данные.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        if ($this->studentMissing) {
            $message = $this->messages()['user.missing'];

            throw new HttpResponseException(response()->json([
                'message' => $message,
                'errors' => ['user' => [$message]],
            ], 404));
        }

        $message = $this->messages()['user.unauthorized'];

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => ['user' => [$message]],
        ], 403));
    }
}
