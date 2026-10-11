<?php

namespace app\controllers;

use app\components\filters\RateLimitFilter;
use app\enums\VerificationPurpose;
use app\forms\EmailConfirmForm;
use app\forms\LoginForm;
use app\forms\PasswordResetRequestForm;
use app\forms\ResetPasswordForm;
use app\forms\SignupForm;
use app\forms\SmsLoginForm;
use app\forms\VerifyCodeForm;
use app\services\AuthService;
use DomainException;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Регистрация, подтверждение контактов, вход, восстановление пароля.
 */
class AuthController extends Controller
{
    public $layout = 'auth';

    public function __construct($id, $module, private readonly AuthService $auth, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $params = Yii::$app->params;
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['actions' => ['logout'], 'allow' => true, 'roles' => ['@']],
                    ['actions' => ['confirm-email', 'resend-code'], 'allow' => true],
                    [
                        'actions' => ['signup', 'verify-phone', 'login', 'sms-login', 'request-password-reset', 'reset-password'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ],
                'denyCallback' => fn() => $this->goHome(),
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['logout' => ['post'], 'resend-code' => ['post']],
            ],
            'rateLimit' => [
                'class' => RateLimitFilter::class,
                'limits' => [
                    'login' => $params['rateLimit.login'] ?? [10, 600],
                    'sms-login' => $params['rateLimit.login'] ?? [10, 600],
                    'signup' => $params['rateLimit.signup'] ?? [5, 3600],
                    'verify-phone' => [20, 600],
                    'reset-password' => [20, 600],
                    'request-password-reset' => [10, 3600],
                ],
            ],
        ];
    }

    public function actionSignup(): Response|string
    {
        $form = new SignupForm();
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $user = $this->auth->signup($form);
                if ($form->isPhone()) {
                    Yii::$app->session->setFlash('info', 'Мы отправили SMS с кодом подтверждения.');
                    return $this->redirect(['verify-phone', 'phone' => $user->phone]);
                }
                return $this->render('check-email', ['email' => $user->email]);
            } catch (DomainException $e) {
                $form->addError('login', $e->getMessage());
            }
        }
        return $this->render('signup', ['model' => $form]);
    }

    public function actionVerifyPhone(?string $phone = null): Response|string
    {
        $form = new VerifyCodeForm(['target' => $phone]);
        if ($form->load(Yii::$app->request->post())) {
            $user = $this->auth->verifyPhone($form);
            if ($user) {
                Yii::$app->user->login($user, (int)Yii::$app->params['auth.rememberMeDuration']);
                Yii::$app->session->setFlash('success', 'Телефон подтверждён. Добро пожаловать!');
                return $this->redirect(['/cabinet/index']);
            }
        }
        return $this->render('verify-phone', [
            'model' => $form,
            'resendIn' => $form->target ? $this->auth->secondsUntilResend((string)$form->target) : 0,
        ]);
    }

    /**
     * Повторная отправка кода (rate limit — в AuthService).
     */
    public function actionResendCode(): Response
    {
        $request = Yii::$app->request;
        $target = (string)$request->post('target');
        $purpose = VerificationPurpose::tryFrom((string)$request->post('purpose', 'signup')) ?? VerificationPurpose::Signup;
        try {
            $this->auth->resendCode($target, $purpose);
            $message = 'Код отправлен повторно.';
            $ok = true;
        } catch (DomainException $e) {
            $message = $e->getMessage();
            $ok = false;
        }
        if ($request->isAjax) {
            return $this->asJson(['success' => $ok, 'message' => $message]);
        }
        Yii::$app->session->setFlash($ok ? 'success' : 'error', $message);
        return $this->redirect($request->referrer ?: ['verify-phone', 'phone' => $target]);
    }

    public function actionConfirmEmail(?string $token = null): Response|string
    {
        $form = new EmailConfirmForm(['token' => $token]);
        if (!$form->validate()) {
            return $this->render('email-confirmed', ['success' => false, 'message' => 'Ссылка подтверждения некорректна.']);
        }
        try {
            $user = $this->auth->confirmEmail((string)$form->token);
            if (Yii::$app->user->isGuest) {
                Yii::$app->user->login($user, (int)Yii::$app->params['auth.rememberMeDuration']);
            }
            return $this->render('email-confirmed', ['success' => true, 'message' => 'Email подтверждён.']);
        } catch (DomainException $e) {
            return $this->render('email-confirmed', ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function actionLogin(): Response|string
    {
        $form = new LoginForm();
        if ($form->load(Yii::$app->request->post()) && $this->auth->login($form)) {
            return $this->goBack(['/cabinet/index']);
        }
        $form->password = '';
        return $this->render('login', ['model' => $form]);
    }

    /**
     * Вход по одноразовому SMS-коду (опционально, ТЗ 22 вопрос 2).
     */
    public function actionSmsLogin(): Response|string
    {
        $form = new SmsLoginForm(['scenario' => SmsLoginForm::SCENARIO_REQUEST]);
        $codeSent = false;
        $request = Yii::$app->request;

        if ($request->isPost) {
            $step = $request->post('step', 'request');
            $form->scenario = $step === 'verify' ? SmsLoginForm::SCENARIO_VERIFY : SmsLoginForm::SCENARIO_REQUEST;
            $form->load($request->post());
            try {
                if ($step === 'verify') {
                    if ($this->auth->smsLogin($form)) {
                        return $this->goBack(['/cabinet/index']);
                    }
                    $codeSent = true;
                } else {
                    $codeSent = $this->auth->requestSmsLogin($form);
                }
            } catch (DomainException $e) {
                $form->addError('phone', $e->getMessage());
                $codeSent = $step === 'verify';
            }
        }
        return $this->render('sms-login', ['model' => $form, 'codeSent' => $codeSent]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    public function actionRequestPasswordReset(): Response|string
    {
        $form = new PasswordResetRequestForm();
        if ($form->load(Yii::$app->request->post())) {
            try {
                $channel = $this->auth->requestPasswordReset($form);
                if ($channel !== null) {
                    if ($form->isPhone()) {
                        Yii::$app->session->setFlash('info', 'Если номер зарегистрирован, на него отправлен код.');
                        return $this->redirect(['reset-password', 'phone' => $form->login]);
                    }
                    Yii::$app->session->setFlash('info', 'Если email зарегистрирован, на него отправлена ссылка для восстановления.');
                    return $this->redirect(['login']);
                }
            } catch (DomainException $e) {
                $form->addError('login', $e->getMessage());
            }
        }
        return $this->render('request-password-reset', ['model' => $form]);
    }

    public function actionResetPassword(?string $token = null, ?string $phone = null): Response|string
    {
        $form = new ResetPasswordForm([
            'scenario' => $token ? ResetPasswordForm::SCENARIO_TOKEN : ResetPasswordForm::SCENARIO_CODE,
            'token' => $token,
            'phone' => $phone,
        ]);
        if ($form->load(Yii::$app->request->post()) && $this->auth->resetPassword($form)) {
            Yii::$app->session->setFlash('success', 'Пароль изменён. Войдите с новым паролем.');
            return $this->redirect(['login']);
        }
        return $this->render('reset-password', ['model' => $form]);
    }
}
