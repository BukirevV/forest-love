<?php

namespace app\modules\admin\controllers;

use app\models\TreeSpecies;
use app\rbac\Rbac;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Response;

/**
 * Справочник пород: CRUD, сортировка, деактивация.
 */
class SpeciesController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_SPECIES;
    protected array $postActions = ['delete', 'toggle', 'sort'];

    public function actionIndex(): string
    {
        $query = TreeSpecies::find();
        if ($q = Yii::$app->request->get('q')) {
            $query->andWhere(['or', ['like', 'name', $q], ['like', 'latin_name', $q]]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['sort' => SORT_ASC, 'name' => SORT_ASC]],
            'pagination' => ['pageSize' => 100],
        ]);
        return $this->render('index', ['dataProvider' => $provider, 'q' => $q]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel(TreeSpecies::class, $id);
        return $this->render('view', ['model' => $model, 'plantingsCount' => (int)$model->getPlantings()->count()]);
    }

    public function actionCreate(): Response|string
    {
        $model = new TreeSpecies(['is_active' => true, 'sort' => 0]);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Порода добавлена.');
            return $this->redirect(['index']);
        }
        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel(TreeSpecies::class, $id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Сохранено.');
            return $this->redirect(['index']);
        }
        return $this->render('update', ['model' => $model]);
    }

    /**
     * Удаление запрещено при наличии посадок — предлагается деактивация.
     */
    public function actionDelete(int $id): Response
    {
        /** @var TreeSpecies $model */
        $model = $this->findModel(TreeSpecies::class, $id);
        if ($model->delete() === false) {
            $this->flashErrors($model);
        } else {
            Yii::$app->session->setFlash('success', 'Порода удалена.');
        }
        return $this->redirect(['index']);
    }

    public function actionToggle(int $id): Response
    {
        /** @var TreeSpecies $model */
        $model = $this->findModel(TreeSpecies::class, $id);
        $model->is_active = !$model->is_active;
        $model->save(false, ['is_active', 'updated_at']);
        return $this->redirect(['index']);
    }

    /**
     * Сохранение порядка: POST order[] = id (по порядку).
     */
    public function actionSort(): Response
    {
        foreach (array_values((array)Yii::$app->request->post('order', [])) as $i => $id) {
            TreeSpecies::updateAll(['sort' => ($i + 1) * 10], ['id' => (int)$id]);
        }
        \yii\caching\TagDependency::invalidate(Yii::$app->cache, TreeSpecies::CACHE_TAG);
        return Yii::$app->request->isAjax ? $this->asJson(['success' => true]) : $this->redirect(['index']);
    }
}
