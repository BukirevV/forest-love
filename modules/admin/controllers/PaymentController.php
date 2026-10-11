<?php

namespace app\modules\admin\controllers;

use app\models\Payment;
use app\models\search\PaymentSearch;
use app\rbac\Rbac;
use app\services\PaymentService;
use Yii;
use yii\web\Response;

/**
 * Платежи: список, просмотр, ручная сверка статуса с провайдером.
 */
class PaymentController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_PAYMENTS;
    protected array $postActions = ['sync'];

    public function __construct($id, $module, private readonly PaymentService $payments, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $search = new PaymentSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['payment' => $this->findModel(Payment::class, $id)]);
    }

    public function actionSync(int $id): Response
    {
        /** @var Payment $payment */
        $payment = $this->findModel(Payment::class, $id);
        try {
            $before = $payment->status;
            $this->payments->sync($payment);
            Yii::$app->session->setFlash('success', "Сверка выполнена: {$before} → {$payment->status}.");
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', 'Ошибка сверки: ' . $e->getMessage());
        }
        return $this->redirect(['view', 'id' => $id]);
    }
}
