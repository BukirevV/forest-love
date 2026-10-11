<?php

namespace app\models\search;

use app\enums\PlacementType;
use app\models\AdPlacement;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AdPlacementSearch extends Model
{
    public $campaign_id;
    public $type;
    public $is_active;

    public function rules(): array
    {
        return [
            ['campaign_id', 'integer'],
            ['type', 'in', 'range' => PlacementType::values()],
            ['is_active', 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return (new AdPlacement())->attributeLabels();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = AdPlacement::find()->with('campaign');
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['priority' => SORT_DESC, 'id' => SORT_DESC]],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }
        $query->andFilterWhere([
            'campaign_id' => $this->campaign_id,
            'type' => $this->type,
            'is_active' => $this->is_active === '' || $this->is_active === null ? null : (bool)$this->is_active,
        ]);
        return $dataProvider;
    }
}
