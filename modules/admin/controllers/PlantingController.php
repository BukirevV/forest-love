<?php

namespace app\modules\admin\controllers;

use app\enums\NamedStatus;
use app\models\AdCampaign;
use app\models\Planting;
use app\models\search\PlantingSearch;
use app\rbac\Rbac;
use app\services\AdStatsService;
use app\services\MapDataService;
use app\services\ModerationService;
use app\services\NamedTreeService;
use Yii;
use yii\web\Response;

/**
 * Все карточки: просмотр, скрытие, привязка к кампании, ручное управление именным статусом.
 */
class PlantingController extends BaseAdminController
{
    protected string $permission = Rbac::ROLE_ADMIN;
    protected array $postActions = ['hide', 'restore', 'set-campaign', 'set-named'];

    public function __construct(
        $id,
        $module,
        private readonly ModerationService $moderation,
        private readonly NamedTreeService $namedTrees,
        private readonly AdStatsService $adStats,
        private readonly MapDataService $mapData,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $search = new PlantingSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
            'campaigns' => AdCampaign::find()->select(['title', 'id'])->indexBy('id')->column(),
        ]);
    }

    public function actionView(int $id): string
    {
        /** @var Planting $planting */
        $planting = $this->findModel(Planting::class, $id);
        return $this->render('view', [
            'planting' => $planting,
            'campaigns' => AdCampaign::find()->select(['title', 'id'])->indexBy('id')->column(),
        ]);
    }

    public function actionHide(int $id): Response
    {
        $planting = $this->findModel(Planting::class, $id);
        $this->moderation->hidePlanting($planting, $this->moderator(), Yii::$app->request->post('comment'));
        Yii::$app->session->setFlash('success', 'Карточка скрыта.');
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionRestore(int $id): Response
    {
        $planting = $this->findModel(Planting::class, $id);
        $this->moderation->restorePlanting($planting, $this->moderator());
        Yii::$app->session->setFlash('success', 'Карточка возвращена на модерацию.');
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionSetCampaign(int $id): Response
    {
        /** @var Planting $planting */
        $planting = $this->findModel(Planting::class, $id);
        $old = $planting->ad_campaign_id;
        $planting->ad_campaign_id = Yii::$app->request->post('ad_campaign_id') ?: null;
        if ($planting->save(true, ['ad_campaign_id', 'updated_at'])) {
            foreach (array_filter([$old, $planting->ad_campaign_id]) as $cid) {
                $this->adStats->invalidate((int)$cid);
            }
            $this->mapData->invalidateTree((int)$planting->id);
            Yii::$app->session->setFlash('success', 'Привязка к кампании обновлена.');
        } else {
            $this->flashErrors($planting);
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionSetNamed(int $id): Response
    {
        /** @var Planting $planting */
        $planting = $this->findModel(Planting::class, $id);
        $status = NamedStatus::tryFrom((string)Yii::$app->request->post('named_status')) ?? NamedStatus::None;
        $until = Yii::$app->request->post('named_until');
        $this->namedTrees->setStatus($planting, $status, $until ? strtotime($until . ' 23:59:59') : null);
        Yii::$app->session->setFlash('success', 'Именной статус изменён.');
        return $this->redirect(['view', 'id' => $id]);
    }
}
