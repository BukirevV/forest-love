<?php

namespace app\components\payment;

use app\enums\PaymentStatus;
use app\models\Payment;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Request;

/**
 * Шлюз для провайдера с REST API в стиле «платёж + redirect-подтверждение + вебхук»
 * (схема совместима с ЮKassa: POST /payments, GET /payments/{id}, уведомления payment.*).
 * Финальный статус всегда перепроверяется запросом к API — данные вебхука не принимаются на веру.
 */
class HttpPaymentGateway extends Component implements PaymentGatewayInterface
{
    public ?string $apiUrl = null;
    public ?string $shopId = null;
    public ?string $secretKey = null;
    public ?string $webhookSecret = null;
    /** @var string[] IP/подсети провайдера; пусто — не проверять */
    public array $allowedIps = [];
    public string $code = 'http';
    public int $timeout = 15;

    public function init(): void
    {
        parent::init();
        if (!$this->apiUrl || !$this->shopId || !$this->secretKey) {
            throw new InvalidConfigException('Не заданы параметры платёжного провайдера (PAYMENT_*).');
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    private function client(): Client
    {
        return new Client([
            'baseUrl' => rtrim($this->apiUrl, '/'),
            'transport' => 'yii\httpclient\CurlTransport',
            'requestConfig' => ['format' => Client::FORMAT_JSON],
            'responseConfig' => ['format' => Client::FORMAT_JSON],
        ]);
    }

    private function auth(): string
    {
        return 'Basic ' . base64_encode($this->shopId . ':' . $this->secretKey);
    }

    public function createPayment(Payment $payment, string $returnUrl): string
    {
        $response = $this->client()->post('payments', [
            'amount' => ['value' => number_format((float)$payment->amount, 2, '.', ''), 'currency' => $payment->currency],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => "Именное дерево, карточка #{$payment->planting_id}",
            'metadata' => ['payment_id' => $payment->id],
        ], [
            'Authorization' => $this->auth(),
            'Idempotence-Key' => $payment->idempotency_key,
        ])->setOptions(['timeout' => $this->timeout])->send();

        if (!$response->isOk) {
            Yii::error("Payment create error #{$payment->id}: {$response->content}", 'payment');
            throw new \RuntimeException('Платёжный сервис временно недоступен.');
        }
        $data = $response->data;
        $payment->markPending((string)$data['id'], $data);
        Yii::info("Payment #{$payment->id} created at provider: {$data['id']}", 'payment');

        return (string)($data['confirmation']['confirmation_url'] ?? $returnUrl);
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        if ($this->allowedIps && !$this->ipAllowed((string)$request->userIP)) {
            throw new ForbiddenHttpException('IP не разрешён.');
        }
        $body = $request->getRawBody();
        if ($this->webhookSecret) {
            $signature = (string)$request->headers->get('X-Signature');
            if ($signature === '' || !hash_equals(hash_hmac('sha256', $body, $this->webhookSecret), $signature)) {
                throw new ForbiddenHttpException('Неверная подпись.');
            }
        }
        $data = json_decode($body, true);
        $id = $data['object']['id'] ?? null;
        if (!$id) {
            throw new BadRequestHttpException('Некорректное уведомление.');
        }
        // Перепроверяем статус у провайдера
        return $this->getStatus((string)$id);
    }

    public function getStatus(string $providerPaymentId): PaymentResult
    {
        $response = $this->client()->get('payments/' . rawurlencode($providerPaymentId), null, [
            'Authorization' => $this->auth(),
        ])->setOptions(['timeout' => $this->timeout])->send();

        if (!$response->isOk) {
            throw new \RuntimeException("Не удалось получить статус платежа {$providerPaymentId}.");
        }
        $data = $response->data;
        return new PaymentResult(
            providerPaymentId: (string)$data['id'],
            status: $this->mapStatus((string)$data['status'], $data),
            payload: $data,
            paymentId: isset($data['metadata']['payment_id']) ? (int)$data['metadata']['payment_id'] : null,
            amount: $data['amount']['value'] ?? null,
            currency: $data['amount']['currency'] ?? null,
        );
    }

    private function mapStatus(string $status, array $data): PaymentStatus
    {
        if (!empty($data['refunded_amount']['value']) && (float)$data['refunded_amount']['value'] > 0) {
            return PaymentStatus::Refunded;
        }
        return match ($status) {
            'succeeded' => PaymentStatus::Succeeded,
            'canceled' => PaymentStatus::Canceled,
            'pending', 'waiting_for_capture' => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };
    }

    private function ipAllowed(string $ip): bool
    {
        foreach ($this->allowedIps as $range) {
            $range = trim($range);
            if ($range === $ip) {
                return true;
            }
            if (str_contains($range, '/')) {
                $validator = new \yii\validators\IpValidator(['ranges' => [$range]]);
                if ($validator->validate($ip)) {
                    return true;
                }
            }
        }
        return false;
    }
}
