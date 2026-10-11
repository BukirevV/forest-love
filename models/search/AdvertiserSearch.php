<?php

namespace app\models\search;

use app\models\Advertiser;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AdvertiserSearch extends Model
{
    public $name;

    public function rules(): array
    {
        return [['name', 'string']];
    }

    public function attributeLabels(): array
    {
        return ['name' => 'Наименование'];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Advertiser::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['name' => SORT_ASC]],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }
        $query->andFilterWhere(['or', ['like', 'name', $this->name], ['like', 'legal_name', $this->name]]);
        return $dataProvider;
    }
}
