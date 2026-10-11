<?php

namespace app\jobs;

use app\models\Payment;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\services\NotificationService;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Отложенное уведомление владельца по событию модерации/оплаты
 * (для вызова из мест, где нужно уведомить после коммита транзакции).
 */
class NotifyOwnerJob extends BaseObject implements JobInterface
{
    public string $event = '';
    public int $entityId = 0;
    public ?string $comment = null;

    public function execute($queue): void
    {
        $service = Yii::$container->get(NotificationService::class);
        switch ($this->event) {
            case 'plantingApproved':
                ($p = Planting::findOne($this->entityId)) && $service->plantingApproved($p);
                break;
            case 'plantingRejected':
                ($p = Planting::findOne($this->entityId)) && $service->plantingRejected($p, $this->comment);
                break;
            case 'revisionRejected':
                ($r = PlantingRevision::findOne($this->entityId)) && $service->revisionRejected($r, $this->comment);
                break;
            case 'photoRejected':
                ($ph = PlantingPhoto::findOne($this->entityId)) && $service->photoRejected($ph, $this->comment);
                break;
            case 'paymentSucceeded':
                ($pay = Payment::findOne($this->entityId)) && $service->paymentSucceeded($pay);
                break;
        }
    }
}
