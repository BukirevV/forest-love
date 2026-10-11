<?php

namespace app\models\query;

use app\enums\PhotoStatus;
use app\models\PlantingPhoto;
use yii\db\ActiveQuery;

/**
 * @method PlantingPhoto|null one($db = null)
 * @method PlantingPhoto[] all($db = null)
 */
class PlantingPhotoQuery extends ActiveQuery
{
    private function col(string $name): string
    {
        return PlantingPhoto::tableName() . '.' . $name;
    }

    public function approved(): static
    {
        return $this->andWhere([$this->col('status') => PhotoStatus::Approved->value]);
    }

    public function pending(): static
    {
        return $this->andWhere([$this->col('status') => PhotoStatus::Pending->value]);
    }

    public function chronological(): static
    {
        return $this->orderBy([$this->col('taken_at') => SORT_ASC, $this->col('id') => SORT_ASC]);
    }

    public function byPlanting(int $plantingId): static
    {
        return $this->andWhere([$this->col('planting_id') => $plantingId]);
    }
}
