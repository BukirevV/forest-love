<?php

namespace app\modules\admin\controllers;

use app\enums\PaymentStatus;
use app\models\AdCampaign;
use app\models\Payment;
use app\models\Planting;
use app\models\User;
use app\services\ModerationService;

/**
 * Главная админки: счётчики очередей модерации, платежей, активных кампаний.
 */
class DefaultController extends BaseAdminController
{
    public function __construct($id, $module, private readonly ModerationService $moderation, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $today = strtotime('today');
        return $this->render('index', [
            'queue' => $this->moderation->getQueueCounts(),
            'stats' => [
                'published' => (int)Planting::find()->published()->count(),
                'named' => (int)Planting::find()->published()->named()->count(),
                'users' => (int)User::find()->active()->count(),
                'usersToday' => (int)User::find()->where(['>=', 'created_at', $today])->count(),
                'paymentsPending' => (int)Payment::find()->where(['status' => [PaymentStatus::New->value, PaymentStatus::Pending->value]])->count(),
                'paymentsToday' => (float)Payment::find()->where(['status' => PaymentStatus::Succeeded->value])->andWhere(['>=', 'paid_at', $today])->sum('amount'),
                'campaignsRunning' => (int)AdCampaign::find()->running()->count(),
            ],
        ]);
    }
}
