<?php

namespace app\modules\admin\controllers;

use app\models\User;
use app\rbac\Rbac;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Базовый контроллер админки: доступ по разрешению $permission.
 */
abstract class BaseAdminController extends Controller
{
    /** Разрешение RBAC, необходимое для доступа к контроллеру. */
    protected string $permission = Rbac::MODERATE;

    /** Действия, разрешённые только POST. */
    protected array $postActions = ['delete'];

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => [$this->permission]],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => array_fill_keys($this->postActions, ['post']),
            ],
        ];
    }

    protected function moderator(): User
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        return $user;
    }

    /**
     * @template T of \yii\db\ActiveRecord
     * @param class-string<T> $class
     * @return T
     */
    protected function findModel(string $class, int $id): \yii\db\ActiveRecord
    {
        $model = $class::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Запись не найдена.');
        }
        return $model;
    }

    protected function flashErrors(\yii\base\Model $model): void
    {
        Yii::$app->session->setFlash('error', implode(' ', $model->getFirstErrors()));
    }
}
