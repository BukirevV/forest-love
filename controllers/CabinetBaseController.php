<?php

namespace app\controllers;

use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\User;
use app\rbac\Rbac;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Базовый контроллер личного кабинета: доступ только для роли user,
 * проверка владения через правило RBAC OwnPlantingRule.
 */
abstract class CabinetBaseController extends Controller
{
    public $layout = 'cabinet';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => [Rbac::ROLE_USER]],
                ],
            ],
        ];
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        return $user;
    }

    /**
     * Карточка текущего пользователя; чужая — 403.
     */
    protected function findOwnPlanting(int $id, string $permission = Rbac::PLANTING_UPDATE_OWN): Planting
    {
        $planting = Planting::find()->andWhere([Planting::tableName() . '.id' => $id])->notHidden()->one();
        if (!$planting) {
            throw new NotFoundHttpException('Карточка не найдена.');
        }
        if (!Yii::$app->user->can($permission, ['planting' => $planting])) {
            throw new ForbiddenHttpException('Это не ваша карточка.');
        }
        return $planting;
    }

    protected function findOwnPhoto(int $id): PlantingPhoto
    {
        $photo = PlantingPhoto::findOne($id);
        if (!$photo) {
            throw new NotFoundHttpException('Фотография не найдена.');
        }
        if (!Yii::$app->user->can(Rbac::PHOTO_UPLOAD_OWN, ['planting' => $photo->planting])) {
            throw new ForbiddenHttpException('Это не ваша фотография.');
        }
        return $photo;
    }
}
