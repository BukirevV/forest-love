<?php

namespace app\controllers;

use app\components\filters\RateLimitFilter;
use app\forms\PhotoUploadForm;
use app\rbac\Rbac;
use app\services\PlantingService;
use DomainException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Фотографии карточек: догрузка, удаление, обложка.
 */
class PhotoController extends CabinetBaseController
{
    public function __construct($id, $module, private readonly PlantingService $plantings, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['upload' => ['post'], 'delete' => ['post'], 'set-main' => ['post']],
            ],
            'rateLimit' => [
                'class' => RateLimitFilter::class,
                'limits' => ['upload' => Yii::$app->params['rateLimit.photoUpload'] ?? [30, 3600]],
            ],
        ]);
    }

    /**
     * Догрузка фото в карточку с датой (история роста).
     */
    public function actionUpload(int $planting_id): Response|array
    {
        $planting = $this->findOwnPlanting($planting_id, Rbac::PHOTO_UPLOAD_OWN);
        $form = new PhotoUploadForm();
        $form->load(Yii::$app->request->post());

        try {
            $photo = $this->plantings->addPhoto($planting, $form, $this->currentUser());
            $ok = true;
            $message = 'Фотография загружена и отправлена на модерацию.';
        } catch (DomainException $e) {
            $ok = false;
            $message = $e->getMessage();
        }

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['success' => $ok, 'message' => $message, 'id' => $ok ? $photo->id : null];
        }
        Yii::$app->session->setFlash($ok ? 'success' : 'error', $message);
        return $this->redirect(['/planting/view', 'id' => $planting->id]);
    }

    public function actionDelete(int $id): Response
    {
        $photo = $this->findOwnPhoto($id);
        $plantingId = $photo->planting_id;
        try {
            $this->plantings->deletePhoto($photo, $this->currentUser());
            Yii::$app->session->setFlash('success', 'Фотография удалена.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['/planting/view', 'id' => $plantingId]);
    }

    public function actionSetMain(int $id): Response
    {
        $photo = $this->findOwnPhoto($id);
        try {
            $this->plantings->setMainPhoto($photo, $this->currentUser());
            Yii::$app->session->setFlash('success', 'Обложка обновлена.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['/planting/view', 'id' => $photo->planting_id]);
    }
}
