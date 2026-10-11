<?php

namespace app\models\query;

use app\enums\UserStatus;
use app\models\User;
use yii\db\ActiveQuery;

/**
 * @method User|null one($db = null)
 * @method User[] all($db = null)
 */
class UserQuery extends ActiveQuery
{
    public function active(): static
    {
        return $this->andWhere([User::tableName() . '.status' => UserStatus::Active->value]);
    }
}
