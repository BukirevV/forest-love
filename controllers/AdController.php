<?php

namespace app\controllers;

use app\models\AdCampaign;
use app\models\Planting;
use app\services\AdStatsService;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Публичная карточка рекламы (ТЗ 10.5).
 */
class AdController extends Controller
{
    public function __construct($id, $module, private readonly AdStatsService $stats, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionView(string $slug): string
    {
        $campaign = AdCampaign::find()
            ->publiclyVisible()
            ->andWhere(['slug' => $slug])
            ->with('advertiser')
            ->one();
        if (!$campaign) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $plantings = new ActiveDataProvider([
            'query' => Planting::find()->published()->byCampaign((int)$campaign->id)->with(['species', 'mainPhoto']),
            'sort' => ['defaultOrder' => ['planted_at' => SORT_DESC, 'id' => SORT_DESC]],
            'pagination' => ['pageSize' => 24],
        ]);

        return $this->render('view', [
            'campaign' => $campaign,
            'stats' => $this->stats->getStats($campaign),
            'plantings' => $plantings,
        ]);
    }
}
