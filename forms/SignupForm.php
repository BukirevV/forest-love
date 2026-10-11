<?php

namespace app\forms;

use app\enums\VerificationChannel;
use app\models\User;
use app\validators\PhoneValidator;
use Yii;
use yii\base\Model;

/**
 * Регистрация по email или телефону (ТЗ 7).
 */
class SignupForm extends Model
{
    public ?string $login = null;
    public ?string $password = null;
    public ?string $password_repeat = null;
    public bool|string|null $agree = null;

    /** Нормализованный email или телефон (E.164) после валидации. */
    private ?string $normalizedLogin = null;

    public function rules(): array
    {
        return [
            [['login', 'password', 'password_repeat'], 'required'],
            ['login', 'trim'],
            ['login', 'validateLogin'],
            ['password', 'string', 'min' => (int)(Yii::$app->params['auth.passwordMinLength'] ?? 8), 'max' => 72],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => 'Пароли не совпадают.'],
            ['agree', 'required', 'requiredValue' => 1, 'message' => 'Необходимо согласие на обработку персональных данных.'],
        ];
    }

    public function validateLogin(string $attribute): void
    {
        $value = trim((string)$this->login);
        if (str_contains($value, '@')) {
            $email = mb_strtolower($value);
            if (!(new \yii\validators\EmailValidator())->validate($email)) {
                $this->addError($attribute, 'Некорректный email.');
                return;
            }
            $existing = User::findByEmail($email);
            $this->normalizedLogin = $email;
        } else {
            $phone = PhoneValidator::normalize($value);
            if ($phone === null) {
                $this->addError($attribute, 'Укажите корректный email или номер телефона.');
                return;
            }
            $existing = User::findByPhone($phone);
            $this->normalizedLogin = $phone;
        }

        if ($existing !== null) {
            $this->addError($attribute, 'Пользователь с таким контактом уже зарегистрирован. Войдите или восстановите пароль.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'login' => 'Email или телефон',
            'password' => 'Пароль',
            'password_repeat' => 'Повторите пароль',
            'agree' => 'Я согласен(на) с политикой обработки персональных данных и пользовательским соглашением',
        ];
    }

    public function getChannel(): VerificationChannel
    {
        return str_contains((string)$this->normalizedLogin, '@') ? VerificationChannel::Email : VerificationChannel::Sms;
    }

    public function isPhone(): bool
    {
        return $this->getChannel() === VerificationChannel::Sms;
    }

    public function getNormalizedLogin(): ?string
    {
        return $this->normalizedLogin;
    }
}
