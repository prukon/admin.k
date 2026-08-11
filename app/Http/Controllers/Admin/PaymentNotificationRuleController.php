<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\PreviewPaymentNotificationRuleRequest;
use App\Http\Requests\Admin\StorePaymentNotificationRuleRequest;
use App\Http\Requests\Admin\TestSendPaymentNotificationRuleRequest;
use App\Http\Requests\Admin\UpdatePaymentNotificationRuleRequest;
use App\Models\PaymentNotificationRule;
use App\Models\UserPrice;
use App\Services\PartnerContext;
use App\Services\PaymentNotifications\PaymentNotificationSendService;
use App\Services\PaymentNotifications\PaymentNotificationTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class PaymentNotificationRuleController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly PaymentNotificationTemplateRenderer $renderer,
        private readonly PaymentNotificationSendService $sendService,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $partnerId = $this->requirePartnerId();

        $rules = PaymentNotificationRule::query()
            ->where('partner_id', $partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentNotificationRule $rule) => $this->serializeRule($rule))
            ->values();

        return response()->json([
            'rules' => $rules,
            'variables' => PaymentNotificationTemplateRenderer::availableVariables(),
            'schedule_type_labels' => [
                'fixed' => 'Фиксированный',
                'flexible' => 'Гибкий',
                'postpay' => 'Постоплата',
            ],
            'trigger_type_labels' => [
                PaymentNotificationRule::TRIGGER_DAY_OF_MONTH => 'День месяца',
                PaymentNotificationRule::TRIGGER_DAYS_AFTER_OVERDUE => 'Дней после начала просрочки',
            ],
        ]);
    }

    public function store(StorePaymentNotificationRuleRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $payload = $request->validatedPayload();

        $rule = PaymentNotificationRule::query()->create([
            'partner_id' => $partnerId,
            ...$payload,
        ]);

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh()),
        ], 201);
    }

    public function update(UpdatePaymentNotificationRuleRequest $request, int $id): JsonResponse
    {
        $rule = $this->findPartnerRule($id);
        $rule->update($request->validatedPayload());

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $rule = $this->findPartnerRule($id);
        $rule->delete();

        return response()->json(['ok' => true]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $rule = $this->findPartnerRule($id);

        $enabled = $request->has('is_enabled')
            ? (bool) filter_var($request->input('is_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : ! $rule->is_enabled;

        $rule->update(['is_enabled' => $enabled]);

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh()),
        ]);
    }

    public function preview(PreviewPaymentNotificationRuleRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userPrice = $this->resolveOptionalUserPrice($partnerId, $request->integer('users_price_id') ?: null);

        if ($userPrice !== null) {
            $rendered = $this->renderer->render(
                (string) $request->input('subject_template'),
                (string) $request->input('body_html_template'),
                $userPrice
            );
        } else {
            $rendered = $this->renderer->renderDemo(
                (string) $request->input('subject_template'),
                (string) $request->input('body_html_template')
            );
        }

        return response()->json([
            'ok' => true,
            'subject' => $rendered['subject'],
            'body_html' => $rendered['body_html'],
            'email_html' => $rendered['email_html'],
            'variables' => $rendered['variables'],
            'is_demo' => $userPrice === null,
        ]);
    }

    public function testSend(TestSendPaymentNotificationRuleRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userPrice = $this->resolveOptionalUserPrice($partnerId, $request->integer('users_price_id') ?: null);

        try {
            $sent = $this->sendService->sendTest(
                $partnerId,
                (string) $request->input('to_email'),
                (string) $request->input('subject_template'),
                (string) $request->input('body_html_template'),
                $userPrice
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Не удалось отправить тестовое письмо: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Тестовое письмо отправлено.',
            'subject' => $sent['subject'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()?->can('setPrices.paymentNotifications.manage')) {
            abort(403);
        }
    }

    private function findPartnerRule(int $id): PaymentNotificationRule
    {
        $partnerId = $this->requirePartnerId();

        return PaymentNotificationRule::query()
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function resolveOptionalUserPrice(int $partnerId, ?int $usersPriceId): ?UserPrice
    {
        if ($usersPriceId === null || $usersPriceId <= 0) {
            return null;
        }

        return UserPrice::query()
            ->with(['user', 'team', 'lessonPackage'])
            ->where('users_prices.id', $usersPriceId)
            ->whereHas('user', static fn ($q) => $q->where('partner_id', $partnerId))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(PaymentNotificationRule $rule): array
    {
        return [
            'id' => (int) $rule->id,
            'name' => (string) $rule->name,
            'is_enabled' => (bool) $rule->is_enabled,
            'trigger_type' => (string) $rule->trigger_type,
            'trigger_value' => (int) $rule->trigger_value,
            'billing_month_offset' => (int) $rule->billing_month_offset,
            'schedule_types' => $rule->normalizedScheduleTypes(),
            'subject_template' => (string) $rule->subject_template,
            'body_html_template' => (string) $rule->body_html_template,
            'sort_order' => (int) $rule->sort_order,
            'updated_at' => optional($rule->updated_at)?->toDateTimeString(),
        ];
    }
}
