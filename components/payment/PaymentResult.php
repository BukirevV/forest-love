<?php

namespace app\components\payment;

use app\enums\PaymentStatus;

/**
 * Результат обращения к провайдеру / разбора вебхука.
 */
final class PaymentResult
{
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly PaymentStatus $status,
        public readonly array $payload = [],
        public readonly ?int $paymentId = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
    ) {
    }
}
