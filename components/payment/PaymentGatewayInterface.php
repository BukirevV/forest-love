<?php

namespace app\components\payment;

use app\models\Payment;
use yii\web\Request;

/**
 * Платёжный шлюз (ТЗ 9.1). Провайдер уточняется (ТЗ 22, вопрос 1).
 */
interface PaymentGatewayInterface
{
    /** Код провайдера, сохраняется в payment.provider и используется в URL вебхука. */
    public function getCode(): string;

    /**
     * Создаёт платёж у провайдера. Должен установить $payment->provider_payment_id
     * (через Payment::markPending) и вернуть URL для перенаправления пользователя.
     */
    public function createPayment(Payment $payment, string $returnUrl): string;

    /**
     * Разбор и проверка уведомления (подпись, IP). Бросает
     * \yii\web\BadRequestHttpException / ForbiddenHttpException при ошибке проверки.
     */
    public function handleWebhook(Request $request): PaymentResult;

    /** Актуальный статус платежа у провайдера. */
    public function getStatus(string $providerPaymentId): PaymentResult;
}
