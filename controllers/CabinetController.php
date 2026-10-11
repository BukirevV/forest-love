<?php

namespace app\controllers;

use app\enums\PlantingStatus;
use app\enums\VerificationPurpose;
use app\forms\ProfileForm;
use app\forms\VerifyCodeForm;
use app\models\ModerationLog;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use app\services\AuthService;
use DomainException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Сводка кабинета и профиль.
 */
class CabinetController extends CabinetBaseController
{
    public function __construct($id, $module, private readonly AuthService $auth, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['confirm-phone' => ['post']]],
        ]);
    }

    public function actionIndex(): string
    {
        $user = $this->currentUser();
        $counts = Planting::find()
            ->select(['status', 'cnt' => 'COUNT(*)'])
            ->byOwner($user->id)
            ->notHidden()
            ->groupBy('status')
            ->asArray()
            ->all();
        $byStatus = array_fill_keys(PlantingStatus::values(), 0);
        foreach ($counts as $row) {
            $byStatus[$row['status']] = (int)$row['cnt'];
        }

        // Последние решения модерации по объектам пользователя
        $plantingIds = Planting::find()->select('id')->byOwner($user->id)->column();
        $revisionIds = $plantingIds ? PlantingRevision::find()->select('id')->where(['planting_id' => $plantingIds])->column() : [];
        $photoIds = $plantingIds ? PlantingPhoto::find()->select('id')->where(['planting_id' => $plantingIds])->column() : [];
        $notifications = ModerationLog::find()
            ->where(['or',
                ['entity_type' => 'planting', 'entity_id' => $plantingIds ?: [0]],
                ['entity_type' => 'planting_revision', 'entity_id' => $revisionIds ?: [0]],
                ['entity_type' => 'planting_photo', 'entity_id' => $photoIds ?: [0]],
            ])
            ->orderBy(['id' => SORT_DESC])
            ->limit(10)
            ->all();

        return $this->render('index', [
            'user' => $user,
            'byStatus' => $byStatus,
            'namedCount' => (int)Planting::find()->byOwner($user->id)->named()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function actionProfile(): Response|string
    {
        $user = $this->currentUser();
        $form = new ProfileForm($user);
        $phoneForm = new VerifyCodeForm();

        if ($form->load(Yii::$app->request->post())) {
            try {
                $messages = $this->auth->updateProfile($form);
                if ($messages) {
                    Yii::$app->session->setFlash('success', implode(' ', $messages));
                    if ($form->isPhoneChanged()) {
                        Yii::$app->session->set('pendingPhone', $form->phone);
                    }
                    return $this->refresh();
                }
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        $phoneForm->target = Yii::$app->session->get('pendingPhone');

        return $this->render('profile', ['model' => $form, 'phoneForm' => $phoneForm, 'user' => $user]);
    }

    /**
     * Подтверждение нового телефона кодом из SMS.
     */
    public function actionConfirmPhone(): Response
    {
        $form = new VerifyCodeForm();
        $form->load(Yii::$app->request->post());
        if ($this->auth->confirmPhoneChange($this->currentUser(), $form)) {
            Yii::$app->session->remove('pendingPhone');
            Yii::$app->session->setFlash('success', 'Новый телефон подтверждён.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $form->getFirstErrors()) ?: 'Код неверен.');
        }
        return $this->redirect(['profile']);
    }

    /**
     * Повторная отправка кода на новый контакт.
     */
    public function actionResendContactCode(): Response
    {
        try {
            $this->auth->resendCode((string)Yii::$app->request->post('target'), VerificationPurpose::ContactChange);
            Yii::$app->session->setFlash('success', 'Код отправлен повторно.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['profile']);
    }
}
