<?php

namespace app\models\search;

use app\enums\PhotoStatus;
use app\models\PlantingPhoto;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class PlantingPhotoSearch extends Model
{
    public $status;
    public $planting_id;
    public $user_id;
    public $uploaded_from;
    public $uploaded_to;

    public function rules(): array
    {
        return [
            [['planting_id', 'user_id'], 'integer'],
            ['status', 'in', 'range' => PhotoStatus::values()],
            [['uploaded_from', 'uploaded_to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new PlantingPhoto())->attributeLabels(), [
            'uploaded_from' => 'Загружено с',
            'uploaded_to' => 'Загружено по',
        ]);
    }

    public function search(array $params, int $pageSize = 48): ActiveDataProvider
    {
        $query = PlantingPhoto::find()->with(['planting.species', 'user']);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_ASC]],
            'pagination' => ['pageSize' => $pageSize],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'status' => $this->status,
            'planting_id' => $this->planting_id,
            'user_id' => $this->user_id,
        ]);
        if ($this->uploaded_from) {
            $query->andWhere(['>=', 'created_at', strtotime($this->uploaded_from . ' 00:00:00')]);
        }
        if ($this->uploaded_to) {
            $query->andWhere(['<=', 'created_at', strtotime($this->uploaded_to . ' 23:59:59')]);
        }
        return $dataProvider;
    }
}
