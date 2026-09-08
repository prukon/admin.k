<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;

final class ContractPathTimelineBuilder
{
    /**
     * @return list<array{key: string, label: string, state: string, hint: string, at: ?string}>
     */
    public function schematicPdfSteps(): array
    {
        return $this->withDemoStates($this->pdfStepMeta(), [
            Contract::STATUS_DRAFT => 'done',
            Contract::STATUS_SENT => 'done',
            Contract::STATUS_OPENED => 'active',
            Contract::STATUS_SIGNED => 'pending',
        ]);
    }

    /**
     * @return list<array{key: string, label: string, state: string, hint: string, at: ?string}>
     */
    public function schematicTemplateSteps(): array
    {
        $states = [
            'admin_sent' => 'done',
            Contract::STATUS_AWAITING_CLIENT_FILL => 'active',
            Contract::STATUS_GENERATING_PDF => 'pending',
            Contract::STATUS_DRAFT => 'pending',
            Contract::STATUS_SENT => 'pending',
            Contract::STATUS_OPENED => 'pending',
            Contract::STATUS_SIGNED => 'pending',
        ];

        return $this->withDemoStates($this->templateStepMeta(), $states);
    }

    /**
     * @return list<array{key: string, label: string, state: string, hint: string, at: ?string}>
     */
    public function schematicOtherSteps(): array
    {
        return [
            $this->step(
                Contract::STATUS_REVOKED,
                Contract::$STATUS_RU[Contract::STATUS_REVOKED],
                'pending',
                'Школа сняла договор. 70 ₽ вернутся только до заполнения формы.',
            ),
            $this->step(
                Contract::STATUS_EXPIRED,
                Contract::$STATUS_RU[Contract::STATUS_EXPIRED],
                'pending',
                'Ссылка Подпислона просрочена. Можно отправить на подпись снова.',
            ),
            $this->step(
                Contract::STATUS_FAILED,
                Contract::$STATUS_RU[Contract::STATUS_FAILED],
                'failed',
                'Сбой отправки в Подпислон. Можно отправить снова.',
            ),
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, hint: string, at: ?string}>
     */
    public function build(Contract $contract): array
    {
        $meta = $contract->isTemplateMode() ? $this->templateStepMeta() : $this->pdfStepMeta();
        [$currentKey, $currentState] = $this->currentKeyAndState($contract);
        $keys = array_keys($meta);
        $currentIndex = array_search($currentKey, $keys, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $allDone = $contract->status === Contract::STATUS_SIGNED && $currentState === 'done';
        $steps = [];

        foreach ($keys as $index => $key) {
            $state = 'pending';
            if ($allDone || $index < $currentIndex) {
                $state = 'done';
            } elseif ($index === $currentIndex) {
                $state = $currentState;
            }

            $hint = $meta[$key]['hint'];
            if ($state === 'failed') {
                $hint = (Contract::$STATUS_RU[$contract->status] ?? $contract->status)
                    .'. '.$hint;
            }

            $steps[] = $this->step(
                $key,
                $meta[$key]['label'],
                $state,
                $hint,
                $this->timestampForStep($contract, $key, $state),
            );
        }

        return $steps;
    }

    public function title(Contract $contract): string
    {
        return $contract->isTemplateMode()
            ? 'Путь с формой клиенту'
            : 'Путь с готовым PDF';
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    private function pdfStepMeta(): array
    {
        return [
            Contract::STATUS_DRAFT => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_DRAFT],
                'hint' => 'PDF загружен. Можно отправить SMS на подпись.',
            ],
            Contract::STATUS_SENT => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_SENT],
                'hint' => 'SMS ушло. Клиент ещё не открыл ссылку.',
            ],
            Contract::STATUS_OPENED => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_OPENED],
                'hint' => 'Родитель открыл договор, но ещё не подписал.',
            ],
            Contract::STATUS_SIGNED => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_SIGNED],
                'hint' => 'Родитель подписал договор. Можно скачать подписанный PDF.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    private function templateStepMeta(): array
    {
        return [
            'admin_sent' => [
                'label' => 'Админ отправил договор родителю',
                'hint' => 'Списалось 70 ₽. Родителю ушло письмо, в кабинете появился договор на заполнение и подпись.',
            ],
            Contract::STATUS_AWAITING_CLIENT_FILL => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_AWAITING_CLIENT_FILL],
                'hint' => "Родитель заполнил данные и нажал \"Сформировать договор\".\nСистема собирает PDF из шаблона. Обычно несколько секунд.",
            ],
            Contract::STATUS_GENERATING_PDF => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_GENERATING_PDF],
                'hint' => 'Система собирает PDF из шаблона. Обычно несколько секунд.',
            ],
            Contract::STATUS_DRAFT => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_DRAFT],
                'hint' => "PDF готов. Родитель может прочитать и проверить заполненный договор.\nРодитель должен нажать \"Подписать договор\".\nПосле этого ему будет отправлена SMS на подпись.",
            ],
            Contract::STATUS_SENT => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_SENT],
                'hint' => 'SMS ушло. Клиент ещё не открыл ссылку.',
            ],
            Contract::STATUS_OPENED => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_OPENED],
                'hint' => 'Родитель открыл договор, но ещё не подписал.',
            ],
            Contract::STATUS_SIGNED => [
                'label' => Contract::$STATUS_RU[Contract::STATUS_SIGNED],
                'hint' => 'Родитель подписал договор. Можно скачать подписанный PDF.',
            ],
        ];
    }

    /**
     * @param  array<string, array{label: string, hint: string}>  $meta
     * @param  array<string, string>  $states
     * @return list<array{key: string, label: string, state: string, hint: string, at: ?string}>
     */
    private function withDemoStates(array $meta, array $states): array
    {
        $steps = [];
        foreach ($meta as $key => $item) {
            $steps[] = $this->step(
                $key,
                $item['label'],
                $states[$key] ?? 'pending',
                $item['hint'],
            );
        }

        return $steps;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function currentKeyAndState(Contract $contract): array
    {
        $status = (string) $contract->status;

        if ($status === Contract::STATUS_SIGNED) {
            return [Contract::STATUS_SIGNED, 'done'];
        }

        if ($status === Contract::STATUS_REVOKED) {
            $key = $this->revokedCurrentKey($contract);

            return [$key, 'failed'];
        }

        if ($status === Contract::STATUS_EXPIRED) {
            return [Contract::STATUS_SENT, 'failed'];
        }

        if ($status === Contract::STATUS_FAILED) {
            return [Contract::STATUS_SENT, 'failed'];
        }

        if ($contract->isTemplateMode() && $status === Contract::STATUS_AWAITING_CLIENT_FILL) {
            return [Contract::STATUS_AWAITING_CLIENT_FILL, 'active'];
        }

        if ($status === Contract::STATUS_GENERATING_PDF) {
            return [Contract::STATUS_GENERATING_PDF, 'active'];
        }

        if (in_array($status, [
            Contract::STATUS_DRAFT,
            Contract::STATUS_SENT,
            Contract::STATUS_OPENED,
        ], true)) {
            return [$status, 'active'];
        }

        if ($contract->isTemplateMode()) {
            return [Contract::STATUS_AWAITING_CLIENT_FILL, 'active'];
        }

        return [Contract::STATUS_DRAFT, 'active'];
    }

    private function revokedCurrentKey(Contract $contract): string
    {
        if ($contract->isTemplateMode() && empty($contract->source_pdf_path)) {
            return Contract::STATUS_AWAITING_CLIENT_FILL;
        }

        return Contract::STATUS_SENT;
    }

    private function timestampForStep(Contract $contract, string $key, string $state): ?string
    {
        if ($state === 'pending') {
            return null;
        }

        if ($key === Contract::STATUS_SIGNED && $contract->signed_at) {
            return $contract->signed_at->format('d.m.Y H:i');
        }

        if ($key === 'admin_sent' && $contract->created_at) {
            return $contract->created_at->format('d.m.Y H:i');
        }

        if ($key === Contract::STATUS_AWAITING_CLIENT_FILL && $contract->created_at) {
            return $contract->created_at->format('d.m.Y H:i');
        }

        if ($key === Contract::STATUS_DRAFT && $contract->isPdfMode() && $contract->created_at) {
            return $contract->created_at->format('d.m.Y H:i');
        }

        if ($state !== 'pending' && $contract->updated_at) {
            return $contract->updated_at->format('d.m.Y H:i');
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, state: string, hint: string, at: ?string}
     */
    private function step(string $key, string $label, string $state, string $hint, ?string $at = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'state' => $state,
            'hint' => $hint,
            'at' => $at,
        ];
    }
}
