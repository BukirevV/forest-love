<?php

namespace app\services;

use app\enums\NamedStatus;
use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\models\Payment;
use app\models\Planting;
use DomainException;
use Yii;

/**
 * Именное размещение: заказ, активация после оплаты, истечение срока (ТЗ 9.2, 4.4).
 */
class NamedTreeService
{
    public function __construct(private readonly MapDataService $mapData)
    {
    }

    /**
     * Перевод карточки в awaiting_payment при создании заказа.
     */
    public function markAwaitingPayment(Planting $planting): void
    {
        if (!$planting->canOrderNamed()) {
            throw new DomainException('Для этой карточки нельзя оформить именное размещение.');
        }
        if ($planting->named_status !== NamedStatus::Active->value) {
            $planting->named_status = NamedStatus::AwaitingPayment->value;
            $planting->save(false, ['named_status', 'updated_at']);
        }
    }

    /**
     * Активация после успешной оплаты. Идемпотентна относительно платежа:
     * вызывается только когда Payment::markSucceeded() вернул true.
     */
    public function activate(Payment $payment): void
    {
        $planting = $payment->planting;
        if (!$planting) {
            return;
        }
        $meta = $payment->getMeta();
        $days = isset($meta['days']) && $meta['days'] ? (int)$meta['days'] : null;

        // Продление: если уже активно со сроком — прибавляем к текущему сроку
        $base = ($planting->isNamed() && $planting->named_until) ? (int)$planting->named_until : time();

        $planting->named_status = NamedStatus::Active->value;
        $planting->named_until = $days ? $base + $days * 86400 : null;
        if (!empty($meta['title'])) {
            $planting->title = mb_substr((string)$meta['title'], 0, 150);
        }
        $planting->save(false, ['named_status', 'named_until', 'title', 'updated_at']);

        Yii::info("Planting #{$planting->id} became named (payment #{$payment->id})", 'payment');
        if ($planting->isPublished()) {
            $this->mapData->invalidate();
        }
    }

    /**
     * Откат статуса при неуспешной оплате / возврате.
     */
    public function onPaymentFailed(Payment $payment): void
    {
        $planting = $payment->planting;
        if (!$planting || $planting->named_status !== NamedStatus::AwaitingPayment->value) {
            return;
        }
        $hasOtherPending = Payment::find()
            ->where(['planting_id' => $planting->id, 'type' => PaymentType::NamedTree->value])
            ->andWhere(['status' => [PaymentStatus::New->value, PaymentStatus::Pending->value]])
            ->andWhere(['<>', 'id', $payment->id])
            ->exists();
        if (!$hasOtherPending) {
            $planting->named_status = NamedStatus::None->value;
            $planting->save(false, ['named_status', 'updated_at']);
        }
    }

    public function onRefunded(Payment $payment): void
    {
        $planting = $payment->planting;
        if ($planting && $planting->named_status === NamedStatus::Active->value) {
            $planting->named_status = NamedStatus::None->value;
            $planting->named_until = null;
            $planting->save(false, ['named_status', 'named_until', 'updated_at']);
            $this->mapData->invalidate();
        }
    }

    /**
     * Ручное управление именным статусом (админка).
     */
    public function setStatus(Planting $planting, NamedStatus $status, ?int $until = null): void
    {
        $planting->named_status = $status->value;
        $planting->named_until = $status === NamedStatus::Active ? $until : $planting->named_until;
        $planting->save(false, ['named_status', 'named_until', 'updated_at']);
        $this->mapData->invalidate();
    }

    /**
     * Перевод истёкших размещений в expired. Возвращает количество.
     */
    public function expire(): int
    {
        $count = Planting::updateAll(
            ['named_status' => NamedStatus::Expired->value, 'updated_at' => time()],
            ['and',
                ['named_status' => NamedStatus::Active->value],
                ['not', ['named_until' => null]],
                ['<', 'named_until', time()],
            ]
        );
        if ($count > 0) {
            $this->mapData->invalidate();
        }
        return $count;
    }
}
