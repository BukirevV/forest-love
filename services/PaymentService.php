<?php

namespace app\services;

use app\components\payment\PaymentGatewayInterface;
use app\components\payment\PaymentResult;
use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\forms\NamedOrderForm;
use app\models\Payment;
use app\models\Planting;
use app\models\User;
use DomainException;
use Yii;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Request;

/**
 * Платежи: создание с ключом идемпотентности, обработка вебхука, сверка статусов (ТЗ 9.2).
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly NamedTreeService $namedTrees,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Создаёт платёж за именное размещение и возвращает [Payment, URL для перехода к оплате].
     *
     * @return array{0: Payment, 1: string}
     */
    public function createNamedPayment(Planting $planting, NamedOrderForm $form, User $user): array
    {
        if ((int)$planting->user_id !== (int)$user->id) {
            throw new DomainException('Оплатить можно только свою карточку.');
        }
        if (!$form->validate()) {
            throw new DomainException(implode(' ', $form->getFirstErrors()));
        }
        $tariff = $form->getTariff();

        $payment = new Payment([
            'user_id' => $user->id,
            'planting_id' => $planting->id,
            'type' => PaymentType::NamedTree->value,
            'amount' => $tariff['price'],
            'currency' => $tariff['currency'],
            'status' => PaymentStatus::New->value,
            'provider' => $this->gateway->getCode(),
            'idempotency_key' => Yii::$app->security->generateRandomString(32) . bin2hex(random_bytes(8)),
            'meta' => ['title' => $form->title, 'tariff' => $form->tariff, 'days' => $tariff['days']],
        ]);

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$payment->save()) {
                throw new DomainException(implode(' ', $payment->getFirstErrors()));
            }
            $this->namedTrees->markAwaitingPayment($planting);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        Yii::info("Payment #{$payment->id} created for planting #{$planting->id}, amount {$payment->amount} {$payment->currency}", 'payment');

        $returnUrl = Url::to(['/payment/success', 'id' => $payment->id], true);

        // Бесплатное размещение (цена 0) — активируем сразу
        if ((float)$payment->amount <= 0) {
            $this->applyResult($payment, new PaymentResult('free_' . $payment->id, PaymentStatus::Succeeded, ['free' => true]));
            return [$payment, $returnUrl];
        }

        try {
            $redirect = $this->gateway->createPayment($payment, $returnUrl);
        } catch (\Throwable $e) {
            Yii::error("Payment #{$payment->id} gateway error: {$e->getMessage()}", 'payment');
            $payment->markFailed(['error' => $e->getMessage()]);
            $this->namedTrees->onPaymentFailed($payment);
            throw new DomainException('Платёжный сервис временно недоступен. Попробуйте позже.');
        }
        return [$payment, $redirect];
    }

    /**
     * Обработка уведомления провайдера.
     */
    public function handleWebhook(string $provider, Request $request): Payment
    {
        if ($provider !== $this->gateway->getCode()) {
            throw new NotFoundHttpException('Неизвестный провайдер.');
        }
        $result = $this->gateway->handleWebhook($request);

        $payment = Payment::findOne(['provider_payment_id' => $result->providerPaymentId])
            ?? ($result->paymentId ? Payment::findOne($result->paymentId) : null);
        if (!$payment) {
            Yii::warning("Webhook for unknown payment {$result->providerPaymentId}", 'payment');
            throw new BadRequestHttpException('Платёж не найден.');
        }
        $this->applyResult($payment, $result);
        return $payment;
    }

    /**
     * Применение результата. Повторные уведомления не создают повторных эффектов:
     * строка платежа блокируется (SELECT … FOR UPDATE), активация выполняется
     * только при фактическом переходе в succeeded.
     */
    public function applyResult(Payment $payment, PaymentResult $result): Payment
    {
        $effects = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            /** @var Payment $locked */
            $locked = Payment::findBySql(
                'SELECT * FROM ' . Payment::tableName() . ' WHERE id = :id FOR UPDATE',
                [':id' => $payment->id]
            )->one();

            if ($result->amount !== null && abs((float)$result->amount - (float)$locked->amount) > 0.001) {
                Yii::error("Payment #{$locked->id}: amount mismatch {$result->amount} vs {$locked->amount}", 'payment');
                throw new BadRequestHttpException('Сумма платежа не совпадает.');
            }

            switch ($result->status) {
                case PaymentStatus::Succeeded:
                    if ($locked->markSucceeded($result->payload)) {
                        $this->namedTrees->activate($locked);
                        $effects = 'succeeded';
                    }
                    break;
                case PaymentStatus::Failed:
                case PaymentStatus::Canceled:
                    if ($locked->markFailed($result->payload, $result->status)) {
                        $this->namedTrees->onPaymentFailed($locked);
                    }
                    break;
                case PaymentStatus::Refunded:
                    if ($locked->status !== PaymentStatus::Refunded->value) {
                        $locked->status = PaymentStatus::Refunded->value;
                        $locked->payload = $result->payload;
                        $locked->save(false);
                        $this->namedTrees->onRefunded($locked);
                    }
                    break;
                case PaymentStatus::Pending:
                case PaymentStatus::New:
                    if (!$locked->isFinal() && !$locked->provider_payment_id) {
                        $locked->markPending($result->providerPaymentId, $result->payload);
                    }
                    break;
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        Yii::info("Payment #{$locked->id}: provider status {$result->status->value}, local {$locked->status}", 'payment');
        if ($effects === 'succeeded') {
            $this->notifications->paymentSucceeded($locked);
        }
        $payment->refresh();
        return $payment;
    }

    /**
     * Ручная/фоновая сверка статуса с провайдером.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->isFinal() || !$payment->provider_payment_id) {
            return $payment;
        }
        $result = $this->gateway->getStatus($payment->provider_payment_id);
        return $this->applyResult($payment, $result);
    }

    /**
     * Сверка зависших платежей (cron payment/sync).
     *
     * @return array{checked: int, changed: int, errors: int}
     */
    public function syncStale(int $olderThan = 900, int $limit = 200): array
    {
        $stats = ['checked' => 0, 'changed' => 0, 'errors' => 0];
        foreach (Payment::find()->stale($olderThan)->limit($limit)->each(50) as $payment) {
            /** @var Payment $payment */
            $stats['checked']++;
            $before = $payment->status;
            try {
                $this->sync($payment);
                if ($payment->status !== $before) {
                    $stats['changed']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Yii::error("Payment sync #{$payment->id}: {$e->getMessage()}", 'payment');
            }
        }
        return $stats;
    }
}
