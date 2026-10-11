<?php

namespace app\rbac;

use app\models\Planting;
use app\models\PlantingPhoto;
use yii\rbac\Rule;

/**
 * Проверка владения карточкой (или фото — по его карточке).
 * Использование: Yii::$app->user->can(Rbac::PLANTING_UPDATE_OWN, ['planting' => $planting]).
 */
class OwnPlantingRule extends Rule
{
    public $name = 'isPlantingOwner';

    public function execute($user, $item, $params): bool
    {
        $planting = $params['planting'] ?? null;
        if ($planting === null && isset($params['photo']) && $params['photo'] instanceof PlantingPhoto) {
            $planting = $params['photo']->planting;
        }
        if (!$planting instanceof Planting) {
            return false;
        }
        return (int)$planting->user_id === (int)$user;
    }
}
