<?php

namespace app\models\query;

use app\enums\CampaignStatus;
use app\models\AdCampaign;
use yii\db\ActiveQuery;

/**
 * @method AdCampaign|null one($db = null)
 * @method AdCampaign[] all($db = null)
 */
class AdCampaignQuery extends ActiveQuery
{
    private function col(string $name): string
    {
        return AdCampaign::tableName() . '.' . $name;
    }

    /**
     * Кампания активна и текущая дата в интервале starts_at…ends_at.
     */
    public function running(?int $now = null): static
    {
        $now ??= time();
        return $this->andWhere([$this->col('status') => CampaignStatus::Active->value])
            ->andWhere(['or', [$this->col('starts_at') => null], ['<=', $this->col('starts_at'), $now]])
            ->andWhere(['or', [$this->col('ends_at') => null], ['>=', $this->col('ends_at'), $now]]);
    }

    /**
     * Публично доступные карточки рекламы (активные, приостановленные и завершённые).
     */
    public function publiclyVisible(): static
    {
        return $this->andWhere(['<>', $this->col('status'), CampaignStatus::Draft->value]);
    }
}
