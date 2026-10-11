<?php

namespace app\forms;

use app\models\User;
use yii\base\Model;

/**
 * Вход по email/телефону и паролю.
 */
class LoginForm extends Model
{
    public ?string $login = null;
    public ?string $password = null;
    public bool|string $rememberMe = true;

    private User|false|null $_user = false;

    public function rules(): array
    {
        return [
            [['login', 'password'], 'required'],
            ['login', 'trim'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    public function validatePassword(string $attribute): void
    {
        if ($this->hasErrors()) {
            return;
        }
        $user = $this->getUser();
        if (!$user || !$user->validatePassword((string)$this->password)) {
            $this->addError($attribute, 'Неверный логин или пароль.');
            return;
        }
        if ($user->isBlocked()) {
            $this->addError('login', 'Учётная запись заблокирована.');
            return;
        }
        if (!$user->isActive()) {
            $this->addError('login', 'Контакт не подтверждён. Завершите регистрацию — подтвердите email или телефон.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'login' => 'Email или телефон',
            'password' => 'Пароль',
            'rememberMe' => 'Запомнить меня',
        ];
    }

    public function getUser(): ?User
    {
        if ($this->_user === false) {
            $this->_user = User::findByLogin((string)$this->login);
        }
        return $this->_user;
    }
}
