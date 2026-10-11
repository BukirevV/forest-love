<?php

namespace app\modules\admin\controllers;

use app\components\storage\StorageInterface;
use app\enums\ModerationAction;
use app\enums\PhotoStatus;
use app\enums\PlantingStatus;
use app\enums\RevisionStatus;
use app\forms\ModerationForm;
use app\models\ModerationLog;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\models\search\PlantingPhotoSearch;
use app\models\search\PlantingRevisionSearch;
use app\models\search\PlantingSearch;
use app\services\ModerationService;
use DomainException;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Очередь модерации: новые карточки, изменения, фотографии (ТЗ 11).
 */
class ModerationController extends BaseAdminController
{
    protected array $postActions = ['approve', 'reject', 'bulk-photos'];

    public function __construct($id, $module, private readonly ModerationService $moderation, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * Очередь с вкладками: plantings | revisions | photos.
     */
    public function actionIndex(string $tab = 'plantings'): string
    {
        $params = Yii::$app->request->queryParams;
        $counts = $this->moderation->getQueueCounts();

        switch ($tab) {
            case 'revisions':
                $search = new PlantingRevisionSearch(['status' => RevisionStatus::Pending->value]);
                $provider = $search->search($params);
                break;
            case 'photos':
                return $this->actionPhotos();
            default:
                $tab = 'plantings';
                $search = new PlantingSearch(['status' => PlantingStatus::Pending->value]);
                $provider = $search->search($params);
                $provider->sort->defaultOrder = ['id' => SORT_ASC];
        }

        return $this->render('index', [
            'tab' => $tab,
            'counts' => $counts,
            'searchModel' => $search,
            'dataProvider' => $provider,
        ]);
    }

    /**
     * Карточка целиком: фото, карта, решение.
     */
    public function actionPlanting(int $id): Response|string
    {
        /** @var Planting $planting */
        $planting = $this->findModel(Planting::class, $id);
        $form = new ModerationForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->moderation->decidePlanting($planting, $form, $this->moderator());
                Yii::$app->session->setFlash('success', 'Решение сохранено.');
                return $this->redirect(['index', 'tab' => 'plantings']);
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('planting', [
            'planting' => $planting,
            'model' => $form,
            'log' => $this->logFor('planting', $planting->id),
        ]);
    }

    /**
     * Ревизия: таблица «было/стало», включая ссылки.
     */
    public function actionRevision(int $id): Response|string
    {
        /** @var PlantingRevision $revision */
        $revision = $this->findModel(PlantingRevision::class, $id);
        $form = new ModerationForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->moderation->decideRevision($revision, $form, $this->moderator());
                Yii::$app->session->setFlash('success', 'Решение сохранено.');
                return $this->redirect(['index', 'tab' => 'revisions']);
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('revision', [
            'revision' => $revision,
            'diff' => $revision->getDiff(),
            'model' => $form,
        ]);
    }

    /**
     * Одно фото с решением.
     */
    public function actionPhoto(int $id): Response|string
    {
        /** @var PlantingPhoto $photo */
        $photo = $this->findModel(PlantingPhoto::class, $id);
        $form = new ModerationForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->moderation->decidePhoto($photo, $form, $this->moderator());
                Yii::$app->session->setFlash('success', 'Решение сохранено.');
                return $this->redirect(['index', 'tab' => 'photos']);
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('photo', ['photo' => $photo, 'model' => $form]);
    }

    /**
     * Сетка фото с массовым одобрением.
     */
    public function actionPhotos(): string
    {
        $search = new PlantingPhotoSearch(['status' => PhotoStatus::Pending->value]);
        return $this->render('photos', [
            'tab' => 'photos',
            'counts' => $this->moderation->getQueueCounts(),
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
        ]);
    }

    /**
     * Быстрое одобрение: POST type=planting|revision|photo, id.
     */
    public function actionApprove(): Response
    {
        return $this->quickDecision(ModerationAction::Approve->value);
    }

    /**
     * Быстрое отклонение: POST type, id, comment.
     */
    public function actionReject(): Response
    {
        return $this->quickDecision(ModerationAction::Reject->value);
    }

    /**
     * Массовые действия для фото: POST ids[], action, comment.
     */
    public function actionBulkPhotos(): Response
    {
        $request = Yii::$app->request;
        $ids = array_map('intval', (array)$request->post('ids', []));
        $action = (string)$request->post('action');
        $comment = trim((string)$request->post('comment'));

        if (!$ids) {
            Yii::$app->session->setFlash('warning', 'Не выбрано ни одной фотографии.');
        } elseif ($action === ModerationAction::Reject->value && $comment === '') {
            Yii::$app->session->setFlash('error', 'Для отклонения укажите комментарий.');
        } else {
            $result = $this->moderation->bulkPhotos($ids, $action, $this->moderator(), $comment);
            Yii::$app->session->setFlash('success', "Обработано фотографий: {$result['done']}.");
            if ($result['errors']) {
                Yii::$app->session->setFlash('error', implode('<br>', array_map('yii\helpers\Html::encode', $result['errors'])));
            }
        }
        return $this->redirect(['index', 'tab' => 'photos']);
    }

    /**
     * Оригинал фото (закрытое хранилище) — только для модераторов.
     */
    public function actionOriginal(int $id): Response
    {
        /** @var PlantingPhoto $photo */
        $photo = $this->findModel(PlantingPhoto::class, $id);
        /** @var StorageInterface $storage */
        $storage = Yii::$app->get('storage');
        if (!$storage->existsPrivate($photo->file_path)) {
            throw new NotFoundHttpException('Файл оригинала не найден.');
        }
        return Yii::$app->response->sendFile(
            $storage->getPrivateLocalPath($photo->file_path),
            'photo-' . $photo->id . '.' . pathinfo($photo->file_path, PATHINFO_EXTENSION),
            ['mimeType' => $photo->mime_type, 'inline' => true]
        );
    }

    /**
     * Журнал модерации.
     */
    public function actionLog(): string
    {
        $provider = new \yii\data\ActiveDataProvider([
            'query' => ModerationLog::find()->with('moderator'),
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('log', ['dataProvider' => $provider]);
    }

    private function quickDecision(string $action): Response
    {
        $request = Yii::$app->request;
        $type = (string)$request->post('type');
        $id = (int)$request->post('id');
        $form = new ModerationForm(['action' => $action, 'comment' => $request->post('comment')]);

        if (!$form->validate()) {
            $this->flashErrors($form);
            return $this->redirect($request->referrer ?: ['index']);
        }
        try {
            match ($type) {
                'planting' => $this->moderation->decidePlanting($this->findModel(Planting::class, $id), $form, $this->moderator()),
                'revision' => $this->moderation->decideRevision($this->findModel(PlantingRevision::class, $id), $form, $this->moderator()),
                'photo' => $this->moderation->decidePhoto($this->findModel(PlantingPhoto::class, $id), $form, $this->moderator()),
                default => throw new NotFoundHttpException(),
            };
            Yii::$app->session->setFlash('success', 'Решение сохранено.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        $tab = ['planting' => 'plantings', 'revision' => 'revisions', 'photo' => 'photos'][$type] ?? 'plantings';
        return $this->redirect(['index', 'tab' => $tab]);
    }

    private function logFor(string $type, int $id): array
    {
        return ModerationLog::find()->where(['entity_type' => $type, 'entity_id' => $id])
            ->with('moderator')->orderBy(['id' => SORT_DESC])->all();
    }
}
