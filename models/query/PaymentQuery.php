<?php

namespace app\models\query;

use app\enums\PaymentStatus;
use app\models\Payment;
use yii\db\ActiveQuery;

/**
 * @method Payment|null one($db = null)
 * @method Payment[] all($db = null)
 */
class PaymentQuery extends ActiveQuery
{
    public function byUser(int $userId): static
    {
        return $this->andWhere([Payment::tableName() . '.user_id' => $userId]);
    }

    /**
     * Незавершённые платежи старше $olderThan секунд (для payment/sync).
     */
    public function stale(int $olderThan = 900): static
    {
        return $this->andWhere(['status' => [PaymentStatus::New->value, PaymentStatus::Pending->value]])
            ->andWhere(['not', ['provider_payment_id' => null]])
            ->andWhere(['<', 'created_at', time() - $olderThan]);
    }
}
