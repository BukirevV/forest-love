<?php

namespace app\controllers;

use app\forms\NamedOrderForm;
use app\models\Payment;
use app\models\search\PaymentSearch;
use app\rbac\Rbac;
use app\services\PaymentService;
use DomainException;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Именное размещение и оплата (ТЗ 10.6).
 */
class PaymentController extends CabinetBaseController
{
    public function __construct($id, $module, private readonly PaymentService $payments, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['actions' => ['webhook'], 'allow' => true],
                    ['allow' => true, 'roles' => [Rbac::ROLE_USER]],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['webhook' => ['post']],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if ($action->id === 'webhook') {
            $this->enableCsrfValidation = false; // вебхук без CSRF; проверка подписи и IP — в шлюзе
        }
        return parent::beforeAction($action);
    }

    /**
     * Оформление именного размещения (доступно в любой момент для своих карточек).
     */
    public function actionNamed(int $id): Response|string
    {
        $planting = $this->findOwnPlanting($id, Rbac::PAYMENT_CREATE_OWN);
        $form = new NamedOrderForm(['planting_id' => $planting->id, 'title' => $planting->title]);

        if ($form->load(Yii::$app->request->post())) {
            $form->planting_id = $planting->id;
            try {
                [, $redirect] = $this->payments->createNamedPayment($planting, $form, $this->currentUser());
                return $this->redirect($redirect);
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('named', ['planting' => $planting, 'model' => $form, 'tariffs' => NamedOrderForm::tariffs()]);
    }

    /**
     * Возврат после оплаты. Статус берётся из БД (при необходимости сверяется с провайдером),
     * а не из параметров URL.
     */
    public function actionSuccess(int $id): string
    {
        $payment = $this->findOwnPayment($id);
        if (!$payment->isFinal()) {
            try {
                $payment = $this->payments->sync($payment);
            } catch (\Throwable $e) {
                Yii::warning("Sync on return failed for #{$payment->id}: {$e->getMessage()}", 'payment');
            }
        }
        return $this->render($payment->isSucceeded() || !$payment->isFinal() ? 'success' : 'fail', ['payment' => $payment]);
    }

    public function actionFail(int $id): string
    {
        return $this->render('fail', ['payment' => $this->findOwnPayment($id)]);
    }

    /**
     * Уведомление от провайдера: без CSRF и авторизации, с проверкой подписи и IP.
     */
    public function actionWebhook(string $provider): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $payment = $this->payments->handleWebhook($provider, Yii::$app->request);
        return ['success' => true, 'status' => $payment->status];
    }

    public function actionHistory(): string
    {
        $search = new PaymentSearch();
        $dataProvider = $search->search(Yii::$app->request->queryParams, $this->currentUser()->id);
        return $this->render('history', ['dataProvider' => $dataProvider]);
    }

    private function findOwnPayment(int $id): Payment
    {
        $payment = Payment::findOne($id);
        if (!$payment) {
            throw new NotFoundHttpException('Платёж не найден.');
        }
        if ((int)$payment->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException();
        }
        return $payment;
    }
}
