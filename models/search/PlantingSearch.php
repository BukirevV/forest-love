<?php

namespace app\models\search;

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\enums\RevisionStatus;
use app\models\Planting;
use app\models\PlantingRevision;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Фильтры: статус, порода, владелец, именное, кампания, диапазон дат посадки, наличие ожидающих изменений.
 */
class PlantingSearch extends Model
{
    public $id;
    public $status;
    public $species_id;
    public $user_id;
    public $named_status;
    public $ad_campaign_id;
    public $planted_from;
    public $planted_to;
    public $has_pending_changes;

    public function rules(): array
    {
        return [
            [['id', 'species_id', 'user_id', 'ad_campaign_id'], 'integer'],
            ['status', 'in', 'range' => PlantingStatus::values()],
            ['named_status', 'in', 'range' => NamedStatus::values()],
            [['planted_from', 'planted_to'], 'date', 'format' => 'php:Y-m-d'],
            ['has_pending_changes', 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new Planting())->attributeLabels(), [
            'planted_from' => 'Посажено с',
            'planted_to' => 'Посажено по',
            'has_pending_changes' => 'Есть изменения на модерации',
        ]);
    }

    public function search(array $params, ?int $ownerId = null): ActiveDataProvider
    {
        $t = Planting::tableName();
        $query = Planting::find()->with(['species', 'user', 'mainPhoto', 'adCampaign']);
        if ($ownerId !== null) {
            $query->byOwner($ownerId)->notHidden();
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 30],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            "$t.id" => $this->id,
            "$t.status" => $this->status,
            "$t.species_id" => $this->species_id,
            "$t.user_id" => $this->user_id,
            "$t.named_status" => $this->named_status,
            "$t.ad_campaign_id" => $this->ad_campaign_id,
        ]);
        $query->andFilterWhere(['>=', "$t.planted_at", $this->planted_from]);
        $query->andFilterWhere(['<=', "$t.planted_at", $this->planted_to]);

        if ($this->has_pending_changes !== null && $this->has_pending_changes !== '') {
            $sub = PlantingRevision::find()->select('planting_id')->where(['status' => RevisionStatus::Pending->value]);
            $query->andWhere([$this->has_pending_changes ? 'in' : 'not in', "$t.id", $sub]);
        }

        return $dataProvider;
    }
}
