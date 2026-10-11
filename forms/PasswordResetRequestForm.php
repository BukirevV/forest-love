<?php

namespace app\forms;

use yii\base\Model;

/**
 * Запрос восстановления пароля: email → ссылка, телефон → SMS-код.
 */
class PasswordResetRequestForm extends Model
{
    public ?string $login = null;

    public function rules(): array
    {
        return [
            ['login', 'trim'],
            ['login', 'required'],
            ['login', 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return ['login' => 'Email или телефон'];
    }

    public function isPhone(): bool
    {
        return !str_contains((string)$this->login, '@');
    }
}
