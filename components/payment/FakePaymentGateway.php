<?php

namespace app\components\payment;

use app\enums\PaymentStatus;
use app\models\Payment;
use Yii;
use yii\base\Component;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Request;

/**
 * Эмулятор провайдера для разработки и тестов.
 * createPayment сразу возвращает URL возврата; getStatus считает платёж успешным.
 * Вебхук: JSON {"payment_id": "...", "status": "succeeded"} с заголовком X-Signature = HMAC-SHA256(body, webhookSecret).
 */
class FakePaymentGateway extends Component implements PaymentGatewayInterface
{
    public string $webhookSecret = 'fake-secret';

    /** Статус, который возвращает getStatus (для тестов). */
    public PaymentStatus $simulatedStatus = PaymentStatus::Succeeded;

    public function getCode(): string
    {
        return 'fake';
    }

    public function createPayment(Payment $payment, string $returnUrl): string
    {
        $payment->markPending('fake_' . $payment->idempotency_key, ['emulated' => true]);
        Yii::info("Fake payment #{$payment->id} created", 'payment');
        return $returnUrl;
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $body = $request->getRawBody();
        $signature = (string)$request->headers->get('X-Signature');
        if (!hash_equals(hash_hmac('sha256', $body, $this->webhookSecret), $signature)) {
            throw new ForbiddenHttpException('Неверная подпись.');
        }
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['payment_id']) || empty($data['status'])) {
            throw new BadRequestHttpException('Некорректное тело уведомления.');
        }
        $status = PaymentStatus::tryFrom((string)$data['status']) ?? throw new BadRequestHttpException('Неизвестный статус.');
        return new PaymentResult((string)$data['payment_id'], $status, $data);
    }

    public function getStatus(string $providerPaymentId): PaymentResult
    {
        return new PaymentResult($providerPaymentId, $this->simulatedStatus, ['emulated' => true]);
    }
}
