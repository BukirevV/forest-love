<?php

namespace app\models\search;

use app\enums\CampaignStatus;
use app\models\AdCampaign;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AdCampaignSearch extends Model
{
    public $advertiser_id;
    public $status;
    public $title;
    public $date_from;
    public $date_to;

    public function rules(): array
    {
        return [
            ['advertiser_id', 'integer'],
            ['title', 'string'],
            ['status', 'in', 'range' => CampaignStatus::values()],
            [['date_from', 'date_to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new AdCampaign())->attributeLabels(), ['date_from' => 'Период с', 'date_to' => 'Период по']);
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = AdCampaign::find()->with('advertiser');
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['advertiser_id' => $this->advertiser_id, 'status' => $this->status]);
        $query->andFilterWhere(['like', 'title', $this->title]);
        // Пересечение периода кампании с заданным интервалом
        if ($this->date_from) {
            $query->andWhere(['or', ['ends_at' => null], ['>=', 'ends_at', strtotime($this->date_from . ' 00:00:00')]]);
        }
        if ($this->date_to) {
            $query->andWhere(['or', ['starts_at' => null], ['<=', 'starts_at', strtotime($this->date_to . ' 23:59:59')]]);
        }
        return $dataProvider;
    }
}
