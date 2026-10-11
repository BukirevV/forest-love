<?php

namespace app\services;

use app\enums\ModerationAction;
use app\enums\PhotoStatus;
use app\enums\PlantingStatus;
use app\enums\RevisionStatus;
use app\forms\ModerationForm;
use app\models\ModerationLog;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\models\User;
use DomainException;
use Yii;

/**
 * Модерация карточек, ревизий и фотографий (ТЗ 9.2).
 */
class ModerationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MapDataService $mapData,
        private readonly AdStatsService $adStats,
    ) {
    }

    /**
     * Счётчики очередей модерации.
     *
     * @return array{plantings: int, revisions: int, photos: int}
     */
    public function getQueueCounts(): array
    {
        return [
            'plantings' => (int)Planting::find()->byStatus(PlantingStatus::Pending)->count(),
            'revisions' => (int)PlantingRevision::find()->where(['status' => RevisionStatus::Pending->value])->count(),
            'photos' => (int)PlantingPhoto::find()->pending()->count(),
        ];
    }

    // ---------------------------------------------------------------- Карточки

    public function decidePlanting(Planting $planting, ModerationForm $form, User $moderator): void
    {
        match ($form->action) {
            ModerationAction::Approve->value => $this->approvePlanting($planting, $moderator, $form->comment),
            ModerationAction::Reject->value => $this->rejectPlanting($planting, $moderator, (string)$form->comment),
            ModerationAction::Hide->value => $this->hidePlanting($planting, $moderator, $form->comment),
        };
    }

    /**
     * Публикация. Ожидающие фото одобряются вместе с карточкой (если не отклонены отдельно).
     * Карточка не публикуется без хотя бы одной одобренной фотографии.
     */
    public function approvePlanting(Planting $planting, User $moderator, ?string $comment = null, bool $approvePendingPhotos = true): void
    {
        if ($planting->status !== PlantingStatus::Pending->value) {
            throw new DomainException('Карточка не находится на модерации.');
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($approvePendingPhotos) {
                foreach ($planting->getPhotos()->pending()->all() as $photo) {
                    $this->setPhotoStatus($photo, PhotoStatus::Approved, $moderator, null);
                    ModerationLog::log($photo, ModerationAction::Approve, null, $moderator->id);
                }
            }
            if (!$planting->getApprovedPhotos()->exists()) {
                throw new DomainException('Нельзя опубликовать карточку без одобренной фотографии.');
            }

            $planting->status = PlantingStatus::Approved->value;
            $planting->approved_at = time();
            $planting->moderated_by = $moderator->id;
            $planting->moderation_comment = $comment ?: null;
            $this->ensureMainPhoto($planting);
            $planting->save(false);

            ModerationLog::log($planting, ModerationAction::Approve, $comment, $moderator->id);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $this->afterPublicChange($planting);
        $this->notifications->plantingApproved($planting);
    }

    public function rejectPlanting(Planting $planting, User $moderator, string $comment): void
    {
        if (trim($comment) === '') {
            throw new DomainException('Укажите причину отклонения.');
        }
        if ($planting->status !== PlantingStatus::Pending->value) {
            throw new DomainException('Карточка не находится на модерации.');
        }
        $planting->status = PlantingStatus::Rejected->value;
        $planting->moderated_by = $moderator->id;
        $planting->moderation_comment = $comment;
        $planting->save(false);

        ModerationLog::log($planting, ModerationAction::Reject, $comment, $moderator->id);
        $this->notifications->plantingRejected($planting, $comment);
    }

    public function hidePlanting(Planting $planting, User $moderator, ?string $comment = null): void
    {
        $wasPublished = $planting->isPublished();
        $planting->status = PlantingStatus::Hidden->value;
        $planting->moderated_by = $moderator->id;
        $planting->moderation_comment = $comment ?: $planting->moderation_comment;
        $planting->save(false);

        PlantingRevision::updateAll(
            ['status' => RevisionStatus::Superseded->value, 'updated_at' => time()],
            ['planting_id' => $planting->id, 'status' => RevisionStatus::Pending->value]
        );
        ModerationLog::log($planting, ModerationAction::Hide, $comment, $moderator->id);
        if ($wasPublished) {
            $this->afterPublicChange($planting);
        }
    }

    /**
     * Восстановление скрытой карточки (админ): в pending для повторной проверки.
     */
    public function restorePlanting(Planting $planting, User $moderator): void
    {
        if (!$planting->isHidden()) {
            return;
        }
        $planting->status = PlantingStatus::Pending->value;
        $planting->moderated_by = $moderator->id;
        $planting->save(false);
    }

    // ---------------------------------------------------------------- Ревизии

    public function decideRevision(PlantingRevision $revision, ModerationForm $form, User $moderator): void
    {
        $form->isApprove()
            ? $this->approveRevision($revision, $moderator, $form->comment)
            : $this->rejectRevision($revision, $moderator, (string)$form->comment);
    }

    /**
     * Перенос данных ревизии в карточку.
     */
    public function approveRevision(PlantingRevision $revision, User $moderator, ?string $comment = null): void
    {
        if (!$revision->isPending()) {
            throw new DomainException('Ревизия уже рассмотрена.');
        }
        $planting = $revision->planting;
        $oldCampaign = $planting->ad_campaign_id;

        $tx = Yii::$app->db->beginTransaction();
        try {
            $revision->applyTo($planting);
            $planting->moderated_by = $moderator->id;
            $planting->moderation_comment = $comment ?: null;
            $planting->save(false, ['moderated_by', 'moderation_comment', 'updated_at']);

            $revision->status = RevisionStatus::Approved->value;
            $revision->moderated_by = $moderator->id;
            $revision->moderation_comment = $comment ?: null;
            $revision->save(false);

            ModerationLog::log($revision, ModerationAction::Approve, $comment, $moderator->id);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $this->afterPublicChange($planting);
        if ($oldCampaign) {
            $this->adStats->invalidate((int)$oldCampaign);
        }
        $this->notifications->revisionApproved($revision);
    }

    public function rejectRevision(PlantingRevision $revision, User $moderator, string $comment): void
    {
        if (trim($comment) === '') {
            throw new DomainException('Укажите причину отклонения.');
        }
        if (!$revision->isPending()) {
            throw new DomainException('Ревизия уже рассмотрена.');
        }
        $revision->status = RevisionStatus::Rejected->value;
        $revision->moderated_by = $moderator->id;
        $revision->moderation_comment = $comment;
        $revision->save(false);

        ModerationLog::log($revision, ModerationAction::Reject, $comment, $moderator->id);
        $this->notifications->revisionRejected($revision, $comment);
    }

    // ---------------------------------------------------------------- Фото

    public function decidePhoto(PlantingPhoto $photo, ModerationForm $form, User $moderator): void
    {
        $form->isApprove()
            ? $this->approvePhoto($photo, $moderator)
            : $this->rejectPhoto($photo, $moderator, (string)$form->comment);
    }

    public function approvePhoto(PlantingPhoto $photo, User $moderator): void
    {
        $this->setPhotoStatus($photo, PhotoStatus::Approved, $moderator, null);
        ModerationLog::log($photo, ModerationAction::Approve, null, $moderator->id);

        $planting = $photo->planting;
        if ($this->ensureMainPhoto($planting)) {
            $planting->save(false, ['main_photo_id', 'updated_at']);
        }
        if ($planting->isPublished()) {
            $this->mapData->invalidateTree((int)$planting->id);
        }
    }

    public function rejectPhoto(PlantingPhoto $photo, User $moderator, string $comment): void
    {
        if (trim($comment) === '') {
            throw new DomainException('Укажите причину отклонения.');
        }
        $planting = $photo->planting;
        if ($photo->isApproved() && $planting->isPublished()
            && (int)$planting->getApprovedPhotos()->count() <= 1) {
            throw new DomainException('Это единственная одобренная фотография опубликованной карточки. Скройте карточку целиком.');
        }

        $this->setPhotoStatus($photo, PhotoStatus::Rejected, $moderator, $comment);
        ModerationLog::log($photo, ModerationAction::Reject, $comment, $moderator->id);

        if ((int)$planting->main_photo_id === (int)$photo->id) {
            $planting->main_photo_id = null;
            $this->ensureMainPhoto($planting);
            $planting->save(false, ['main_photo_id', 'updated_at']);
        }
        if ($planting->isPublished()) {
            $this->mapData->invalidateTree((int)$planting->id);
        }
        $this->notifications->photoRejected($photo, $comment);
    }

    /**
     * Массовое решение по фотографиям.
     *
     * @param int[] $ids
     * @return array{done: int, errors: string[]}
     */
    public function bulkPhotos(array $ids, string $action, User $moderator, ?string $comment = null): array
    {
        $done = 0;
        $errors = [];
        foreach (PlantingPhoto::find()->where(['id' => $ids])->pending()->with('planting')->all() as $photo) {
            try {
                $action === ModerationAction::Approve->value
                    ? $this->approvePhoto($photo, $moderator)
                    : $this->rejectPhoto($photo, $moderator, (string)$comment);
                $done++;
            } catch (DomainException $e) {
                $errors[] = "Фото #{$photo->id}: {$e->getMessage()}";
            }
        }
        return ['done' => $done, 'errors' => $errors];
    }

    // ---------------------------------------------------------------- helpers

    private function setPhotoStatus(PlantingPhoto $photo, PhotoStatus $status, User $moderator, ?string $comment): void
    {
        $photo->status = $status->value;
        $photo->moderated_by = $moderator->id;
        $photo->moderation_comment = $comment;
        $photo->save(false, ['status', 'moderated_by', 'moderation_comment', 'updated_at']);
    }

    /**
     * Назначает обложку, если её нет или она не одобрена. Возвращает true, если изменилась.
     */
    private function ensureMainPhoto(Planting $planting): bool
    {
        $current = $planting->main_photo_id ? PlantingPhoto::findOne($planting->main_photo_id) : null;
        if ($current && $current->isApproved()) {
            return false;
        }
        $latest = $planting->getApprovedPhotos()->orderBy(['taken_at' => SORT_DESC, 'id' => SORT_DESC])->one();
        if ($latest && (int)$latest->id !== (int)$planting->main_photo_id) {
            $planting->main_photo_id = $latest->id;
            return true;
        }
        return false;
    }

    private function afterPublicChange(Planting $planting): void
    {
        $this->mapData->invalidate();
        $this->adStats->invalidateForPlanting($planting);
    }
}
