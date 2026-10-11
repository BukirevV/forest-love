<?php

namespace app\modules\admin\controllers;

use app\forms\AdPlacementForm;
use app\models\AdCampaign;
use app\models\AdPlacement;
use app\models\search\AdPlacementSearch;
use app\rbac\Rbac;
use app\services\MapDataService;
use Yii;
use yii\web\Response;

/**
 * Рекламные размещения: точка (выбор координат на карте) и баннер (изображения, позиция), предпросмотр.
 */
class AdPlacementController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_ADS;

    public function __construct($id, $module, private readonly MapDataService $mapData, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $search = new AdPlacementSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
            'campaigns' => $this->campaignList(),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel(AdPlacement::class, $id)]);
    }

    public function actionCreate(?int $campaign_id = null): Response|string
    {
        return $this->save(new AdPlacement(['campaign_id' => $campaign_id]), 'create');
    }

    public function actionUpdate(int $id): Response|string
    {
        return $this->save($this->findModel(AdPlacement::class, $id), 'update');
    }

    /**
     * Предпросмотр баннера/точки поверх карты.
     */
    public function actionPreview(int $id): string
    {
        $this->layout = '@app/views/layouts/map';
        return $this->render('preview', ['model' => $this->findModel(AdPlacement::class, $id)]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel(AdPlacement::class, $id);
        $model->delete();
        $this->mapData->invalidateAds();
        Yii::$app->session->setFlash('success', 'Размещение удалено.');
        return $this->redirect(['index']);
    }

    private function save(AdPlacement $placement, string $view): Response|string
    {
        $form = new AdPlacementForm($placement);
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            $this->mapData->invalidateAds();
            Yii::$app->session->setFlash('success', 'Размещение сохранено.');
            return $this->redirect(['view', 'id' => $placement->id]);
        }
        return $this->render($view, ['model' => $form, 'placement' => $placement, 'campaigns' => $this->campaignList()]);
    }

    private function campaignList(): array
    {
        return AdCampaign::find()->select(['title', 'id'])->orderBy(['id' => SORT_DESC])->indexBy('id')->column();
    }
}
