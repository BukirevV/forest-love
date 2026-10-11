<?php

namespace app\models\query;

use app\enums\CampaignStatus;
use app\enums\PlacementType;
use app\models\AdCampaign;
use app\models\AdPlacement;
use yii\db\ActiveQuery;

/**
 * @method AdPlacement|null one($db = null)
 * @method AdPlacement[] all($db = null)
 */
class AdPlacementQuery extends ActiveQuery
{
    private function col(string $name): string
    {
        return AdPlacement::tableName() . '.' . $name;
    }

    /**
     * Размещение активно, кампания active и текущая дата в интервале (ТЗ 4.6).
     */
    public function active(?int $now = null): static
    {
        $now ??= time();
        $c = AdCampaign::tableName();
        return $this->innerJoinWith('campaign', false)
            ->andWhere([$this->col('is_active') => true])
            ->andWhere(["$c.status" => CampaignStatus::Active->value])
            ->andWhere(['or', ["$c.starts_at" => null], ['<=', "$c.starts_at", $now]])
            ->andWhere(['or', ["$c.ends_at" => null], ['>=', "$c.ends_at", $now]]);
    }

    public function points(): static
    {
        return $this->andWhere([$this->col('type') => PlacementType::Point->value]);
    }

    public function banners(): static
    {
        return $this->andWhere([$this->col('type') => PlacementType::Banner->value]);
    }

    public function inBounds(float $south, float $west, float $north, float $east): static
    {
        $this->andWhere(['between', $this->col('latitude'), min($south, $north), max($south, $north)]);
        if ($west <= $east) {
            $this->andWhere(['between', $this->col('longitude'), $west, $east]);
        } else {
            $this->andWhere(['or', ['>=', $this->col('longitude'), $west], ['<=', $this->col('longitude'), $east]]);
        }
        return $this;
    }

    public function byPriority(): static
    {
        return $this->orderBy([$this->col('priority') => SORT_DESC, $this->col('id') => SORT_DESC]);
    }
}
