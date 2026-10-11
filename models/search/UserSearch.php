<?php

namespace app\models\search;

use app\enums\UserStatus;
use app\models\User;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class UserSearch extends Model
{
    public $id;
    public $email;
    public $phone;
    public $status;
    public $registered_from;
    public $registered_to;

    public function rules(): array
    {
        return [
            ['id', 'integer'],
            [['email', 'phone'], 'string'],
            ['status', 'in', 'range' => UserStatus::values()],
            [['registered_from', 'registered_to'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new User())->attributeLabels(), [
            'registered_from' => 'Зарегистрирован с',
            'registered_to' => 'Зарегистрирован по',
        ]);
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = User::find();
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

        $query->andFilterWhere(['id' => $this->id, 'status' => $this->status]);
        $query->andFilterWhere(['like', 'email', $this->email]);
        $query->andFilterWhere(['like', 'phone', $this->phone ? preg_replace('/[^\d+]/', '', $this->phone) : null]);
        if ($this->registered_from) {
            $query->andWhere(['>=', 'created_at', strtotime($this->registered_from . ' 00:00:00')]);
        }
        if ($this->registered_to) {
            $query->andWhere(['<=', 'created_at', strtotime($this->registered_to . ' 23:59:59')]);
        }
        return $dataProvider;
    }
}
