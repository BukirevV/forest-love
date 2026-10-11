<?php

namespace app\controllers;

use app\components\filters\RateLimitFilter;
use app\forms\PhotoUploadForm;
use app\forms\PlantingForm;
use app\models\search\PlantingSearch;
use app\models\TreeSpecies;
use app\rbac\Rbac;
use app\services\ExifService;
use app\services\PlantingService;
use DomainException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Карточки посадок в личном кабинете (ТЗ 10.6).
 */
class PlantingController extends CabinetBaseController
{
    public function __construct(
        $id,
        $module,
        private readonly PlantingService $plantings,
        private readonly ExifService $exif,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['submit' => ['post'], 'delete' => ['post'], 'exif' => ['post']],
            ],
            'rateLimit' => [
                'class' => RateLimitFilter::class,
                'limits' => [
                    'create' => Yii::$app->params['rateLimit.photoUpload'] ?? [30, 3600],
                    'exif' => [60, 600],
                ],
            ],
        ]);
    }

    public function actionIndex(): string
    {
        $search = new PlantingSearch();
        $dataProvider = $search->search(Yii::$app->request->queryParams, $this->currentUser()->id);
        $dataProvider->query->with(['pendingRevision']);
        return $this->render('index', ['searchModel' => $search, 'dataProvider' => $dataProvider]);
    }

    public function actionCreate(): Response|string
    {
        if (!Yii::$app->user->can(Rbac::PLANTING_CREATE)) {
            throw new \yii\web\ForbiddenHttpException();
        }
        $form = new PlantingForm(['scenario' => PlantingForm::SCENARIO_CREATE]);
        if ($form->load(Yii::$app->request->post())) {
            $form->photo = UploadedFile::getInstance($form, 'photo');
            if ($form->validate()) {
                try {
                    $planting = $this->plantings->create($form, $this->currentUser());
                    Yii::$app->session->setFlash('success', $form->isSubmit()
                        ? 'Карточка отправлена на модерацию. После проверки дерево появится на карте.'
                        : 'Черновик сохранён.');
                    return $this->redirect(['view', 'id' => $planting->id]);
                } catch (DomainException $e) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }
            }
        }
        return $this->render('create', ['model' => $form, 'speciesList' => TreeSpecies::getList()]);
    }

    public function actionView(int $id): string
    {
        $planting = $this->findOwnPlanting($id);
        return $this->render('view', [
            'planting' => $planting,
            'photoForm' => new PhotoUploadForm(),
            'revision' => $planting->pendingRevision,
            'lastRejectedRevision' => $planting->getRevisions()->andWhere(['status' => 'rejected'])->one(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $planting = $this->findOwnPlanting($id);
        $form = new PlantingForm();
        $form->loadFromPlanting($planting);

        if ($form->load(Yii::$app->request->post())) {
            $post = (array)Yii::$app->request->post('PlantingForm', []);
            if (empty($post['links'])) {
                $form->links = []; // все ссылки удалены пользователем
            }
            if ($form->validate()) {
                try {
                    $revision = $this->plantings->update($planting, $form, $this->currentUser());
                    Yii::$app->session->setFlash('success', $revision
                        ? 'Изменения отправлены на модерацию. До их одобрения на карте отображается прежняя версия.'
                        : ($planting->isPublished() ? 'Изменений нет.' : 'Карточка сохранена.'));
                    return $this->redirect(['view', 'id' => $planting->id]);
                } catch (DomainException $e) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }
            }
        }
        return $this->render('update', [
            'model' => $form,
            'planting' => $planting,
            'speciesList' => TreeSpecies::getList(),
        ]);
    }

    public function actionSubmit(int $id): Response
    {
        $planting = $this->findOwnPlanting($id);
        try {
            $this->plantings->submit($planting, $this->currentUser());
            Yii::$app->session->setFlash('success', 'Карточка отправлена на модерацию.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionDelete(int $id): Response
    {
        $planting = $this->findOwnPlanting($id, Rbac::PLANTING_DELETE_OWN);
        try {
            $this->plantings->softDelete($planting, $this->currentUser());
            Yii::$app->session->setFlash('success', 'Карточка удалена.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['index']);
    }

    /**
     * Серверное чтение EXIF — резерв, если клиентское чтение недоступно.
     * Файл не сохраняется.
     */
    public function actionExif(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $file = UploadedFile::getInstanceByName('file');
        if (!$file || $file->hasError) {
            throw new BadRequestHttpException('Файл не передан.');
        }
        if ($file->size > (int)Yii::$app->params['upload.maxFileSize']) {
            throw new BadRequestHttpException('Файл слишком большой.');
        }
        return $this->exif->toClientArray($this->exif->extract($file->tempName));
    }
}
