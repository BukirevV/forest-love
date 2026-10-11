<?php

namespace app\modules\admin\controllers;

use app\forms\AdCampaignForm;
use app\models\AdCampaign;
use app\models\Advertiser;
use app\models\Planting;
use app\models\search\AdCampaignSearch;
use app\rbac\Rbac;
use app\services\AdStatsService;
use app\services\MapDataService;
use Yii;
use yii\web\Response;

/**
 * Рекламные кампании: CRUD, привязка посадок (на карте или списком), статистика.
 */
class AdCampaignController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_ADS;

    public function __construct(
        $id,
        $module,
        private readonly AdStatsService $stats,
        private readonly MapDataService $mapData,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $search = new AdCampaignSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
            'advertisers' => Advertiser::find()->select(['name', 'id'])->indexBy('id')->column(),
        ]);
    }

    public function actionView(int $id): string
    {
        /** @var AdCampaign $campaign */
        $campaign = $this->findModel(AdCampaign::class, $id);
        return $this->render('view', [
            'campaign' => $campaign,
            'stats' => $this->stats->getStats($campaign),
            'placements' => $campaign->getPlacements()->orderBy(['priority' => SORT_DESC])->all(),
        ]);
    }

    public function actionCreate(): Response|string
    {
        return $this->save(new AdCampaign(), 'create');
    }

    public function actionUpdate(int $id): Response|string
    {
        return $this->save($this->findModel(AdCampaign::class, $id), 'update');
    }

    /**
     * Привязка посадок к кампании на карте.
     */
    public function actionPlantings(int $id): Response|string
    {
        /** @var AdCampaign $campaign */
        $campaign = $this->findModel(AdCampaign::class, $id);
        $form = new AdCampaignForm($campaign);

        if (Yii::$app->request->isPost) {
            $ids = array_filter(array_map('intval', explode(',', (string)Yii::$app->request->post('planting_ids', ''))));
            $form->planting_ids = $ids;
            if ($form->save()) {
                $this->mapData->invalidate();
                Yii::$app->session->setFlash('success', 'Привязка посадок сохранена: ' . count($ids) . ' шт.');
                return $this->redirect(['view', 'id' => $id]);
            }
            $this->flashErrors($form);
        }

        $points = Planting::find()
            ->select(['id', 'latitude', 'longitude', 'ad_campaign_id', 'status'])
            ->notHidden()
            ->andWhere(['or', ['ad_campaign_id' => null], ['ad_campaign_id' => $campaign->id]])
            ->limit(20000)
            ->asArray()
            ->all();

        return $this->render('plantings', [
            'campaign' => $campaign,
            'points' => $points,
            'selected' => array_map('intval', (array)$form->planting_ids),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        /** @var AdCampaign $campaign */
        $campaign = $this->findModel(AdCampaign::class, $id);
        Planting::updateAll(['ad_campaign_id' => null], ['ad_campaign_id' => $campaign->id]);
        $campaign->delete();
        $this->mapData->invalidate();
        $this->mapData->invalidateAds();
        Yii::$app->session->setFlash('success', 'Кампания удалена.');
        return $this->redirect(['index']);
    }

    private function save(AdCampaign $campaign, string $view): Response|string
    {
        $form = new AdCampaignForm($campaign);
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            $this->mapData->invalidateAds();
            Yii::$app->session->setFlash('success', 'Кампания сохранена.');
            return $this->redirect(['view', 'id' => $campaign->id]);
        }
        $plantingOptions = Planting::find()
            ->select(['id'])
            ->published()
            ->andWhere(['or', ['ad_campaign_id' => null], ['ad_campaign_id' => $campaign->id ?: 0]])
            ->with('species')
            ->orderBy(['id' => SORT_DESC])
            ->limit(2000)
            ->all();

        return $this->render($view, [
            'model' => $form,
            'campaign' => $campaign,
            'advertisers' => Advertiser::find()->select(['name', 'id'])->indexBy('id')->column(),
            'plantingOptions' => array_combine(
                array_map(static fn(Planting $p) => $p->id, $plantingOptions),
                array_map(static fn(Planting $p) => "#{$p->id} — {$p->getDisplayTitle()}", $plantingOptions)
            ) ?: [],
        ]);
    }
}
