<?php

namespace app\modules\admin\controllers;

use app\models\Advertiser;
use app\models\search\AdvertiserSearch;
use app\rbac\Rbac;
use Yii;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * CRUD рекламодателей.
 */
class AdvertiserController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_ADS;

    public function actionIndex(): string
    {
        $search = new AdvertiserSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel(Advertiser::class, $id)]);
    }

    public function actionCreate(): Response|string
    {
        return $this->save(new Advertiser(), 'create');
    }

    public function actionUpdate(int $id): Response|string
    {
        return $this->save($this->findModel(Advertiser::class, $id), 'update');
    }

    public function actionDelete(int $id): Response
    {
        /** @var Advertiser $model */
        $model = $this->findModel(Advertiser::class, $id);
        if ($model->delete() === false) {
            $this->flashErrors($model);
            return $this->redirect(['view', 'id' => $id]);
        }
        Yii::$app->session->setFlash('success', 'Рекламодатель удалён.');
        return $this->redirect(['index']);
    }

    private function save(Advertiser $model, string $view): Response|string
    {
        if ($model->load(Yii::$app->request->post())) {
            $model->logoFile = UploadedFile::getInstance($model, 'logoFile');
            if ($model->validate()) {
                $model->uploadLogo();
                $model->save(false);
                Yii::$app->session->setFlash('success', 'Сохранено.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }
        return $this->render($view, ['model' => $model]);
    }
}
