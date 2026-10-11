<?php

namespace app\models\query;

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\models\Planting;
use yii\db\ActiveQuery;

/**
 * Scopes карточек посадок (ТЗ 6.4).
 *
 * @method Planting|null one($db = null)
 * @method Planting[] all($db = null)
 */
class PlantingQuery extends ActiveQuery
{
    private function col(string $name): string
    {
        return Planting::tableName() . '.' . $name;
    }

    public function published(): static
    {
        return $this->andWhere([$this->col('status') => PlantingStatus::Approved->value]);
    }

    public function notHidden(): static
    {
        return $this->andWhere(['<>', $this->col('status'), PlantingStatus::Hidden->value]);
    }

    public function byStatus(PlantingStatus|string $status): static
    {
        return $this->andWhere([$this->col('status') => $status instanceof PlantingStatus ? $status->value : $status]);
    }

    public function named(): static
    {
        return $this->andWhere([$this->col('named_status') => NamedStatus::Active->value])
            ->andWhere(['or', [$this->col('named_until') => null], ['>', $this->col('named_until'), time()]]);
    }

    public function byOwner(int $userId): static
    {
        return $this->andWhere([$this->col('user_id') => $userId]);
    }

    public function byCampaign(int $campaignId): static
    {
        return $this->andWhere([$this->col('ad_campaign_id') => $campaignId]);
    }

    public function bySpecies(int $speciesId): static
    {
        return $this->andWhere([$this->col('species_id') => $speciesId]);
    }

    /**
     * Выборка по видимой области карты. Учитывает пересечение антимеридиана (west > east).
     */
    public function inBounds(float $south, float $west, float $north, float $east): static
    {
        $this->andWhere(['between', $this->col('latitude'), min($south, $north), max($south, $north)]);

        if ($west <= $east) {
            $this->andWhere(['between', $this->col('longitude'), $west, $east]);
        } else {
            $this->andWhere(['or',
                ['>=', $this->col('longitude'), $west],
                ['<=', $this->col('longitude'), $east],
            ]);
        }
        return $this;
    }
}
