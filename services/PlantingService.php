<?php

namespace app\services;

use app\components\storage\StorageInterface;
use app\enums\PhotoSource;
use app\enums\PhotoStatus;
use app\enums\PlantingStatus;
use app\enums\RevisionStatus;
use app\forms\PhotoUploadForm;
use app\forms\PlantingForm;
use app\jobs\ProcessPhotoJob;
use app\models\Planting;
use app\models\PlantingLink;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\models\Setting;
use app\models\User;
use DomainException;
use Yii;
use yii\web\UploadedFile;

/**
 * Бизнес-логика карточек посадок (ТЗ 9.2, 4.2).
 */
class PlantingService
{
    public function __construct(
        private readonly ExifService $exif,
        private readonly MapDataService $mapData,
        private readonly AdStatsService $adStats,
    ) {
    }

    private function storage(): StorageInterface
    {
        return Yii::$app->get('storage');
    }

    /**
     * Создание карточки в транзакции: карточка + ссылки + первая фотография.
     */
    public function create(PlantingForm $form, User $user): Planting
    {
        $form->scenario = PlantingForm::SCENARIO_CREATE;
        if (!$form->validate()) {
            throw new DomainException(implode(' ', $form->getFirstErrors()));
        }

        $planting = new Planting($form->getPlantingAttributes());
        $planting->user_id = $user->id;
        $planting->status = $form->isSubmit() ? PlantingStatus::Pending->value : PlantingStatus::Draft->value;

        $photo = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$planting->save()) {
                throw new DomainException(implode(' ', $planting->getFirstErrors()));
            }
            $this->replaceLinks($planting, $form->getLinks());

            /** @var UploadedFile $file */
            $file = $form->photo;
            $exif = $this->exif->extract($file->tempName);
            $takenAt = $form->photo_taken_at ?: ($exif['date'] ?? $planting->planted_at ?? date('Y-m-d'));
            $photo = $this->storePhoto($planting, $user, $file, $takenAt, (string)$form->photo_source, $exif);

            $planting->main_photo_id = $photo->id;
            $planting->save(false, ['main_photo_id', 'updated_at']);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            if ($photo) {
                $this->storage()->deletePrivate($photo->file_path);
            }
            throw $e;
        }

        $this->enqueueProcessing($photo);
        return $planting;
    }

    /**
     * Редактирование с логикой повторной модерации (ТЗ 4.2):
     * - опубликованная карточка: изменения → ревизия pending (предыдущая pending → superseded),
     *   на карте остаётся одобренная версия;
     * - неопубликованная: изменения пишутся в карточку, статус → pending
     *   (черновик остаётся черновиком, пока его не отправят).
     *
     * @return bool true — создана ревизия
     */
    public function update(Planting $planting, PlantingForm $form, User $user): bool
    {
        if (!$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на редактирование этой карточки.');
        }
        $form->scenario = PlantingForm::SCENARIO_UPDATE;
        if (!$form->validate()) {
            throw new DomainException(implode(' ', $form->getFirstErrors()));
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($planting->isPublished()) {
                $this->supersedePendingRevisions($planting);
                $data = $form->getPlantingAttributes();
                $data['links'] = $form->getLinks();

                $revision = new PlantingRevision([
                    'planting_id' => $planting->id,
                    'user_id' => $user->id,
                    'status' => RevisionStatus::Pending->value,
                ]);
                $revision->setData($data);

                if (!$revision->getDiff()) {
                    $tx->rollBack();
                    return false; // изменений нет
                }
                if (!$revision->save()) {
                    throw new DomainException(implode(' ', $revision->getFirstErrors()));
                }
                $tx->commit();
                return true;
            }

            $planting->setAttributes($form->getPlantingAttributes(), false);
            if ($planting->status === PlantingStatus::Rejected->value
                || $planting->status === PlantingStatus::Pending->value
                || $form->isSubmit()) {
                $planting->status = PlantingStatus::Pending->value;
                $planting->moderation_comment = null;
            }
            if (!$planting->save()) {
                throw new DomainException(implode(' ', $planting->getFirstErrors()));
            }
            $this->replaceLinks($planting, $form->getLinks());
            $tx->commit();
            return false;
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Отправка черновика / отклонённой карточки на модерацию.
     */
    public function submit(Planting $planting, User $user): void
    {
        if (!$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на эту карточку.');
        }
        if (!$planting->canBeSubmitted()) {
            throw new DomainException('Карточку нельзя отправить: нужна хотя бы одна фотография, и карточка должна быть черновиком или отклонённой.');
        }
        $planting->status = PlantingStatus::Pending->value;
        $planting->moderation_comment = null;
        $planting->save(false, ['status', 'moderation_comment', 'updated_at']);
    }

    /**
     * Мягкое удаление (hidden).
     */
    public function softDelete(Planting $planting, ?User $user = null): void
    {
        if ($user !== null && !$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на удаление этой карточки.');
        }
        $wasPublished = $planting->isPublished();
        $tx = Yii::$app->db->beginTransaction();
        try {
            $planting->status = PlantingStatus::Hidden->value;
            $planting->save(false, ['status', 'updated_at']);
            $this->supersedePendingRevisions($planting);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        if ($wasPublished) {
            $this->mapData->invalidate();
            $this->adStats->invalidateForPlanting($planting);
        }
    }

    /**
     * Догрузка фотографии в карточку (история роста). Фото уходит на модерацию.
     */
    public function addPhoto(Planting $planting, PhotoUploadForm $form, User $user): PlantingPhoto
    {
        if (!$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на эту карточку.');
        }
        if (!$form->validate()) {
            throw new DomainException(implode(' ', $form->getFirstErrors()));
        }
        $max = (int)Setting::get('upload.maxPhotosPerPlanting', 100);
        if ((int)$planting->getPhotos()->count() >= $max) {
            throw new DomainException("В карточке может быть не более {$max} фотографий.");
        }

        $photo = $this->storePhoto($planting, $user, $form->file, (string)$form->taken_at, (string)$form->source, $form->exif);
        $this->enqueueProcessing($photo);
        return $photo;
    }

    /**
     * Удаление своего фото (кроме последнего одобренного у опубликованной карточки).
     */
    public function deletePhoto(PlantingPhoto $photo, User $user): void
    {
        $planting = $photo->planting;
        if (!$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на эту фотографию.');
        }
        if ($photo->isApproved() && $planting->isPublished()
            && (int)$planting->getApprovedPhotos()->count() <= 1) {
            throw new DomainException('Нельзя удалить последнюю одобренную фотографию опубликованной карточки.');
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            if ((int)$planting->main_photo_id === (int)$photo->id) {
                $next = $planting->getApprovedPhotos()->andWhere(['<>', 'id', $photo->id])->orderBy(['taken_at' => SORT_DESC])->one()
                    ?? $planting->getPhotos()->andWhere(['<>', 'id', $photo->id])->one();
                $planting->main_photo_id = $next?->id;
                $planting->save(false, ['main_photo_id', 'updated_at']);
            }
            $photo->delete();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        if ($planting->isPublished()) {
            $this->mapData->invalidate();
        }
    }

    /**
     * Выбор обложки из одобренных фото.
     */
    public function setMainPhoto(PlantingPhoto $photo, User $user): void
    {
        $planting = $photo->planting;
        if (!$planting->canBeEditedBy($user)) {
            throw new DomainException('Нет прав на эту карточку.');
        }
        if (!$photo->isApproved()) {
            throw new DomainException('Обложкой может быть только одобренная фотография.');
        }
        $planting->main_photo_id = $photo->id;
        $planting->save(false, ['main_photo_id', 'updated_at']);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array<int, array{url: string, label: ?string}> $links
     */
    public function replaceLinks(Planting $planting, array $links): void
    {
        PlantingLink::deleteAll(['planting_id' => $planting->id]);
        foreach (array_values($links) as $i => $item) {
            $link = new PlantingLink([
                'planting_id' => $planting->id,
                'url' => $item['url'],
                'label' => $item['label'] ?? null,
                'sort' => $i,
            ]);
            if (!$link->save()) {
                throw new DomainException('Ссылка №' . ($i + 1) . ': ' . implode(' ', $link->getFirstErrors()));
            }
        }
        $planting->populateRelation('links', $planting->getLinks()->all());
    }

    private function supersedePendingRevisions(Planting $planting): void
    {
        PlantingRevision::updateAll(
            ['status' => RevisionStatus::Superseded->value, 'updated_at' => time()],
            ['planting_id' => $planting->id, 'status' => RevisionStatus::Pending->value]
        );
    }

    /**
     * Сохраняет оригинал в закрытое хранилище и создаёт запись фото (status pending).
     * MIME проверяется по содержимому файла.
     */
    private function storePhoto(Planting $planting, User $user, UploadedFile $file, string $takenAt, string $source, array $exif): PlantingPhoto
    {
        $mime = ImageService::detectMime($file->tempName);
        if (!ImageService::isAllowedMime($mime)) {
            throw new DomainException('Недопустимый формат файла. Разрешены JPEG, PNG, WebP, HEIC.');
        }
        $size = @getimagesize($file->tempName) ?: [0, 0];
        $extension = $file->extension ?: 'jpg';
        $fileSize = (int)$file->size;

        $path = $this->storage()->savePrivate($file->tempName, 'plantings', $extension);

        $photo = new PlantingPhoto([
            'planting_id' => $planting->id,
            'user_id' => $user->id,
            'file_path' => $path,
            'taken_at' => $takenAt,
            'exif_latitude' => $exif['latitude'] ?? null,
            'exif_longitude' => $exif['longitude'] ?? null,
            'source' => $source ?: PhotoSource::Upload->value,
            'width' => (int)$size[0],
            'height' => (int)$size[1],
            'file_size' => $fileSize,
            'mime_type' => $mime,
            'status' => PhotoStatus::Pending->value,
        ]);
        if (!$photo->save()) {
            $this->storage()->deletePrivate($path);
            throw new DomainException(implode(' ', $photo->getFirstErrors()));
        }
        return $photo;
    }

    private function enqueueProcessing(PlantingPhoto $photo): void
    {
        Yii::$app->queue->push(new ProcessPhotoJob(['photoId' => $photo->id]));
    }
}
