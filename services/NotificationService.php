<?php

namespace app\services;

use app\jobs\SendEmailJob;
use app\jobs\SendSmsJob;
use app\models\Payment;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\models\User;
use Yii;
use yii\helpers\Url;

/**
 * Уведомления владельцу: карточка опубликована/отклонена, фото отклонено, оплата прошла.
 * Письмо — если у пользователя подтверждён email, иначе короткое SMS. Отправка через очередь.
 */
class NotificationService
{
    public function plantingApproved(Planting $planting): void
    {
        $this->notify($planting->user, 'plantingApproved', 'Ваше дерево опубликовано на карте', [
            'planting' => $planting,
            'url' => Url::to(['/tree/view', 'id' => $planting->id], true),
        ], 'Лес Любви: ваше дерево опубликовано на карте.');
    }

    public function plantingRejected(Planting $planting, ?string $comment): void
    {
        $this->notify($planting->user, 'plantingRejected', 'Карточка посадки отклонена модератором', [
            'planting' => $planting,
            'comment' => $comment,
            'url' => Url::to(['/planting/view', 'id' => $planting->id], true),
        ], 'Лес Любви: карточка посадки отклонена. Подробности в личном кабинете.');
    }

    public function revisionApproved(PlantingRevision $revision): void
    {
        $this->plantingApproved($revision->planting);
    }

    public function revisionRejected(PlantingRevision $revision, ?string $comment): void
    {
        $this->notify($revision->planting->user, 'plantingRejected', 'Изменения карточки отклонены', [
            'planting' => $revision->planting,
            'comment' => $comment,
            'isRevision' => true,
            'url' => Url::to(['/planting/view', 'id' => $revision->planting_id], true),
        ], 'Лес Любви: изменения карточки отклонены. Подробности в личном кабинете.');
    }

    public function photoRejected(PlantingPhoto $photo, ?string $comment): void
    {
        $this->notify($photo->user, 'photoRejected', 'Фотография отклонена модератором', [
            'photo' => $photo,
            'comment' => $comment,
            'url' => Url::to(['/planting/view', 'id' => $photo->planting_id], true),
        ], 'Лес Любви: фотография отклонена. Подробности в личном кабинете.');
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->notify($payment->user, 'paymentSucceeded', 'Оплата прошла — дерево стало именным', [
            'payment' => $payment,
            'url' => $payment->planting_id ? Url::to(['/tree/view', 'id' => $payment->planting_id], true) : Url::home(true),
        ], 'Лес Любви: оплата получена, дерево стало именным.');
    }

    private function notify(User $user, string $template, string $subject, array $params, string $smsText): void
    {
        $queue = Yii::$app->queue;
        if ($user->email && $user->isEmailVerified()) {
            $queue->push(new SendEmailJob([
                'to' => $user->email,
                'subject' => $subject,
                'template' => $template,
                'params' => $this->serializableParams($params),
            ]));
        } elseif ($user->phone && $user->isPhoneVerified()) {
            $queue->push(new SendSmsJob(['phone' => $user->phone, 'text' => $smsText]));
        }
    }

    /**
     * В очередь передаются только скаляры и ID моделей.
     */
    private function serializableParams(array $params): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            if ($value instanceof \yii\db\ActiveRecord) {
                $result[$key] = ['__model' => $value::class, 'id' => $value->getPrimaryKey()];
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
