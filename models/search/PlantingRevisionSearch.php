<?php

namespace app\models\search;

use app\enums\RevisionStatus;
use app\models\PlantingRevision;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class PlantingRevisionSearch extends Model
{
    public $status;
    public $planting_id;
    public $user_id;

    public function rules(): array
    {
        return [
            [['planting_id', 'user_id'], 'integer'],
            ['status', 'in', 'range' => RevisionStatus::values()],
        ];
    }

    public function attributeLabels(): array
    {
        return (new PlantingRevision())->attributeLabels();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = PlantingRevision::find()->with(['planting.species', 'user']);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_ASC]],
            'pagination' => ['pageSize' => 30],
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
        return $dataProvider;
    }
}
