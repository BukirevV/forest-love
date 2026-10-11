<?php

namespace app\services;

use app\enums\UserStatus;
use app\enums\VerificationChannel;
use app\enums\VerificationPurpose;
use app\forms\LoginForm;
use app\forms\PasswordResetRequestForm;
use app\forms\ProfileForm;
use app\forms\ResetPasswordForm;
use app\forms\SignupForm;
use app\forms\SmsLoginForm;
use app\forms\VerifyCodeForm;
use app\jobs\SendEmailJob;
use app\jobs\SendSmsJob;
use app\models\User;
use app\models\VerificationCode;
use app\rbac\Rbac;
use app\validators\PhoneValidator;
use DomainException;
use Yii;
use yii\helpers\Url;

/**
 * Регистрация, коды подтверждения, вход, восстановление пароля, смена контактов (ТЗ 9.2).
 * Ограничение частоты отправки кодов: не чаще 1 раза в auth.resendInterval секунд
 * и не более auth.maxCodesPerHour в час на адресата и на IP.
 */
class AuthService
{
    public function __construct(private readonly RateLimitService $rateLimit)
    {
    }

    // ---------------------------------------------------------------- Регистрация

    /**
     * Создаёт пользователя в статусе unverified и отправляет код/письмо подтверждения.
     */
    public function signup(SignupForm $form): User
    {
        if (!$form->validate()) {
            throw new DomainException(implode(' ', $form->getFirstErrors()));
        }
        $login = (string)$form->getNormalizedLogin();

        $user = new User();
        $user->status = UserStatus::Unverified->value;
        if ($form->isPhone()) {
            $user->phone = $login;
        } else {
            $user->email = $login;
        }
        $user->setPassword((string)$form->password);
        $user->generateAuthKey();

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$user->save()) {
                throw new DomainException(implode(' ', $user->getFirstErrors()));
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        if ($form->isPhone()) {
            $this->sendSmsCode($user, $login, VerificationPurpose::Signup);
        } else {
            $this->sendEmailToken($user, $login, VerificationPurpose::Signup);
        }
        return $user;
    }

    // ---------------------------------------------------------------- Отправка кодов

    /**
     * @throws DomainException при превышении лимита
     */
    public function sendSmsCode(User $user, string $phone, VerificationPurpose $purpose): void
    {
        $this->guardSending($phone);
        [, $code] = VerificationCode::create($user, VerificationChannel::Sms->value, $purpose->value, $phone);

        $template = Yii::$app->params['sms.templates'][$purpose->value] ?? 'Код: {code}';
        $text = strtr($template, ['{code}' => $code, '{app}' => Yii::$app->params['appName'] ?? 'Лес Любви']);
        Yii::$app->queue->push(new SendSmsJob(['phone' => $phone, 'text' => $text]));
    }

    public function sendEmailToken(User $user, string $email, VerificationPurpose $purpose): void
    {
        $this->guardSending($email);
        [, $token] = VerificationCode::create($user, VerificationChannel::Email->value, $purpose->value, $email);

        if ($purpose === VerificationPurpose::PasswordReset) {
            $template = 'passwordReset';
            $subject = 'Восстановление пароля';
            $url = Url::to(['/auth/reset-password', 'token' => $token], true);
        } else {
            $template = 'confirmEmail';
            $subject = $purpose === VerificationPurpose::ContactChange ? 'Подтверждение нового email' : 'Подтверждение регистрации';
            $url = Url::to(['/auth/confirm-email', 'token' => $token], true);
        }

        Yii::$app->queue->push(new SendEmailJob([
            'to' => $email,
            'subject' => $subject,
            'template' => $template,
            'params' => ['url' => $url, 'email' => $email],
        ]));
    }

    /**
     * Повторная отправка кода/письма.
     */
    public function resendCode(string $target, VerificationPurpose $purpose): void
    {
        $isPhone = !str_contains($target, '@');
        $target = $isPhone ? (string)PhoneValidator::normalize($target) : mb_strtolower(trim($target));

        $user = $purpose === VerificationPurpose::ContactChange
            ? (Yii::$app->user->identity ?? null)
            : User::findByLogin($target);

        if (!$user instanceof User) {
            // Не раскрываем существование учётной записи
            return;
        }
        if ($purpose === VerificationPurpose::Signup && $user->isActive()) {
            return;
        }
        $isPhone
            ? $this->sendSmsCode($user, $target, $purpose)
            : $this->sendEmailToken($user, $target, $purpose);
    }

    public function secondsUntilResend(string $target): int
    {
        return $this->rateLimit->secondsUntilAllowed('code-send:' . $target, (int)Yii::$app->params['auth.resendInterval']);
    }

    private function guardSending(string $target): void
    {
        $params = Yii::$app->params;
        $interval = (int)($params['auth.resendInterval'] ?? 60);
        $perHour = (int)($params['auth.maxCodesPerHour'] ?? 5);

        $wait = $this->rateLimit->throttle('code-send:' . $target, $interval);
        if ($wait > 0) {
            throw new DomainException("Повторная отправка будет доступна через {$wait} с.");
        }
        if (!$this->rateLimit->hit('code-hour:' . $target, $perHour, 3600)) {
            throw new DomainException('Превышено количество отправок кода. Попробуйте через час.');
        }
        $ip = Yii::$app->request instanceof \yii\web\Request ? Yii::$app->request->userIP : null;
        if ($ip && !$this->rateLimit->hit('code-ip:' . $ip, $perHour * 3, 3600)) {
            throw new DomainException('Слишком много запросов кодов с вашего адреса. Попробуйте позже.');
        }
    }

    // ---------------------------------------------------------------- Подтверждение

    /**
     * Подтверждение телефона кодом из SMS (регистрация).
     */
    public function verifyPhone(VerifyCodeForm $form): ?User
    {
        if (!$form->validate()) {
            return null;
        }
        $phone = PhoneValidator::normalize((string)$form->target);
        $code = $phone ? VerificationCode::findActive($phone, VerificationPurpose::Signup->value) : null;

        if (!$code || !$this->checkCode($code, (string)$form->code, $form)) {
            return null;
        }
        $user = $code->user;
        $user->markPhoneVerified();
        $user->save(false);
        $this->activate($user);
        return $user;
    }

    /**
     * Подтверждение email по ссылке (регистрация или смена email).
     */
    public function confirmEmail(string $token): User
    {
        $code = VerificationCode::findByToken($token, VerificationPurpose::Signup->value)
            ?? VerificationCode::findByToken($token, VerificationPurpose::ContactChange->value);
        if (!$code) {
            throw new DomainException('Ссылка подтверждения недействительна или устарела.');
        }
        $user = $code->user;

        $tx = Yii::$app->db->beginTransaction();
        try {
            $code->markUsed();
            if ($code->purpose === VerificationPurpose::ContactChange->value) {
                if (User::find()->where(['email' => $code->target])->andWhere(['<>', 'id', $user->id])->exists()) {
                    throw new DomainException('Этот email уже используется другим пользователем.');
                }
                $user->email = $code->target;
            }
            $user->markEmailVerified();
            $user->save(false);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $this->activate($user);
        return $user;
    }

    /**
     * Подтверждение нового телефона в профиле.
     */
    public function confirmPhoneChange(User $user, VerifyCodeForm $form): bool
    {
        if (!$form->validate()) {
            return false;
        }
        $phone = PhoneValidator::normalize((string)$form->target);
        $code = $phone ? VerificationCode::findActive($phone, VerificationPurpose::ContactChange->value) : null;
        if (!$code || (int)$code->user_id !== (int)$user->id || !$this->checkCode($code, (string)$form->code, $form)) {
            return false;
        }
        if (User::find()->where(['phone' => $phone])->andWhere(['<>', 'id', $user->id])->exists()) {
            $form->addError('target', 'Этот телефон уже используется другим пользователем.');
            return false;
        }
        $user->phone = $phone;
        $user->markPhoneVerified();
        return $user->save(false);
    }

    private function checkCode(VerificationCode $code, string $value, \yii\base\Model $form): bool
    {
        if ($code->isLocked()) {
            $form->addError('code', 'Превышено число попыток. Запросите новый код.');
            return false;
        }
        if (!$code->verify($value)) {
            $left = max(0, (int)Yii::$app->params['auth.codeMaxAttempts'] - $code->attempts);
            $form->addError('code', $left > 0 ? "Неверный код. Осталось попыток: {$left}." : 'Превышено число попыток. Запросите новый код.');
            return false;
        }
        return true;
    }

    /**
     * Активация учётной записи и назначение роли user (ТЗ 15.2).
     */
    public function activate(User $user): void
    {
        if ($user->status === UserStatus::Unverified->value) {
            $user->status = UserStatus::Active->value;
            $user->save(false, ['status', 'updated_at']);
        }
        $auth = Yii::$app->authManager;
        $role = $auth->getRole(Rbac::ROLE_USER);
        if ($role && !$auth->getAssignment(Rbac::ROLE_USER, $user->id)) {
            $auth->assign($role, $user->id);
        }
    }

    // ---------------------------------------------------------------- Вход

    public function login(LoginForm $form): bool
    {
        if (!$form->validate()) {
            return false;
        }
        return $this->doLogin($form->getUser(), (bool)$form->rememberMe);
    }

    public function requestSmsLogin(SmsLoginForm $form): bool
    {
        $form->scenario = SmsLoginForm::SCENARIO_REQUEST;
        if (!$form->validate()) {
            return false;
        }
        $user = User::findByPhone((string)$form->phone);
        if ($user && $user->isActive()) {
            $this->sendSmsCode($user, (string)$user->phone, VerificationPurpose::Login);
        }
        return true; // не раскрываем существование номера
    }

    public function smsLogin(SmsLoginForm $form): bool
    {
        $form->scenario = SmsLoginForm::SCENARIO_VERIFY;
        if (!$form->validate()) {
            return false;
        }
        $code = VerificationCode::findActive((string)$form->phone, VerificationPurpose::Login->value);
        if (!$code || !$this->checkCode($code, (string)$form->code, $form)) {
            if (!$form->hasErrors()) {
                $form->addError('code', 'Код недействителен. Запросите новый.');
            }
            return false;
        }
        $user = $code->user;
        if (!$user->isActive()) {
            $form->addError('phone', 'Учётная запись недоступна.');
            return false;
        }
        return $this->doLogin($user, true);
    }

    private function doLogin(User $user, bool $remember): bool
    {
        $duration = $remember ? (int)(Yii::$app->params['auth.rememberMeDuration'] ?? 2592000) : 0;
        if (!Yii::$app->user->login($user, $duration)) {
            return false;
        }
        $user->last_login_at = time();
        $user->save(false, ['last_login_at']);
        return true;
    }

    // ---------------------------------------------------------------- Восстановление пароля

    /**
     * Отправляет ссылку (email) или SMS-код (телефон). Возвращает канал.
     * Ответ одинаков независимо от существования пользователя.
     */
    public function requestPasswordReset(PasswordResetRequestForm $form): ?VerificationChannel
    {
        if (!$form->validate()) {
            return null;
        }
        $user = User::findByLogin((string)$form->login);
        $channel = $form->isPhone() ? VerificationChannel::Sms : VerificationChannel::Email;

        if ($user && !$user->isBlocked()) {
            if ($channel === VerificationChannel::Sms && $user->phone) {
                $this->sendSmsCode($user, $user->phone, VerificationPurpose::PasswordReset);
            } elseif ($channel === VerificationChannel::Email && $user->email) {
                $this->sendEmailToken($user, $user->email, VerificationPurpose::PasswordReset);
            }
        }
        return $channel;
    }

    public function resetPassword(ResetPasswordForm $form): bool
    {
        if (!$form->validate()) {
            return false;
        }
        if ($form->scenario === ResetPasswordForm::SCENARIO_TOKEN) {
            $code = VerificationCode::findByToken((string)$form->token, VerificationPurpose::PasswordReset->value);
            if (!$code) {
                $form->addError('password', 'Ссылка восстановления недействительна или устарела.');
                return false;
            }
            $code->markUsed();
        } else {
            $phone = PhoneValidator::normalize((string)$form->phone);
            $code = $phone ? VerificationCode::findActive($phone, VerificationPurpose::PasswordReset->value) : null;
            if (!$code || !$this->checkCode($code, (string)$form->code, $form)) {
                if (!$form->hasErrors()) {
                    $form->addError('code', 'Код недействителен. Запросите новый.');
                }
                return false;
            }
        }

        $user = $code->user;
        $user->setPassword((string)$form->password);
        $user->generateAuthKey(); // сброс «запомнить меня» на других устройствах
        // Владение контактом подтверждено
        if ($code->channel === VerificationChannel::Email->value) {
            $user->markEmailVerified();
        } else {
            $user->markPhoneVerified();
        }
        $user->save(false);
        $this->activate($user);
        return true;
    }

    // ---------------------------------------------------------------- Профиль

    /**
     * Сохраняет профиль. Новые email/телефон применяются только после подтверждения.
     *
     * @return string[] сообщения для пользователя
     */
    public function updateProfile(ProfileForm $form): array
    {
        if (!$form->validate()) {
            return [];
        }
        $user = $form->getUser();
        $messages = [];

        $user->display_name = $form->display_name;
        if ($form->new_password) {
            $user->setPassword($form->new_password);
            $messages[] = 'Пароль изменён.';
        }
        $user->save(false);

        if ($form->isEmailChanged() && $form->email) {
            $this->sendEmailToken($user, $form->email, VerificationPurpose::ContactChange);
            $messages[] = "На {$form->email} отправлено письмо для подтверждения. Email изменится после перехода по ссылке.";
        }
        if ($form->isPhoneChanged() && $form->phone) {
            $this->sendSmsCode($user, $form->phone, VerificationPurpose::ContactChange);
            $messages[] = 'На новый номер отправлен код подтверждения.';
        }
        $messages[] = 'Профиль сохранён.';
        return $messages;
    }
}
