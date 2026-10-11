<?php

namespace app\controllers;

use app\models\Planting;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Публичная карточка дерева (ТЗ 10.4).
 */
class TreeController extends Controller
{
    public function actionView(int $id): string
    {
        $tree = Planting::find()
            ->published()
            ->andWhere([Planting::tableName() . '.id' => $id])
            ->with(['species', 'links', 'approvedPhotos', 'mainPhoto', 'adCampaign.advertiser', 'user'])
            ->one();
        if (!$tree) {
            throw new NotFoundHttpException('Дерево не найдено или ещё не опубликовано.');
        }
        return $this->render('view', ['tree' => $tree]);
    }
}
