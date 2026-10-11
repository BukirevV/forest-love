<?php

namespace app\models\search;

use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\models\Payment;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class PaymentSearch extends Model
{
    public $id;
    public $status;
    public $type;
    public $user_id;
    public $date_from;
    public $date_to;

    public function rules(): array
    {
        return [
            [['id', 'user_id'], 'integer'],
            ['status', 'in', 'range' => PaymentStatus::values()],
            ['type', 'in', 'range' => PaymentType::values()],
            [['date_from', 'date_to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new Payment())->attributeLabels(), ['date_from' => 'С', 'date_to' => 'По']);
    }

    public function search(array $params, ?int $userId = null): ActiveDataProvider
    {
        $query = Payment::find()->with(['user', 'planting.species']);
        if ($userId !== null) {
            $query->byUser($userId);
        }
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'status' => $this->status,
            'type' => $this->type,
            'user_id' => $this->user_id,
        ]);
        if ($this->date_from) {
            $query->andWhere(['>=', 'created_at', strtotime($this->date_from . ' 00:00:00')]);
        }
        if ($this->date_to) {
            $query->andWhere(['<=', 'created_at', strtotime($this->date_to . ' 23:59:59')]);
        }
        return $dataProvider;
    }
}
