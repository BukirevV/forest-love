<?php

namespace app\forms;

use Yii;
use yii\base\Model;

/**
 * Установка нового пароля: по токену из письма или по SMS-коду (phone + code).
 */
class ResetPasswordForm extends Model
{
    public const SCENARIO_TOKEN = 'token';
    public const SCENARIO_CODE = 'code';

    public ?string $token = null;
    public ?string $phone = null;
    public ?string $code = null;
    public ?string $password = null;
    public ?string $password_repeat = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_TOKEN => ['token', 'password', 'password_repeat'],
            self::SCENARIO_CODE => ['phone', 'code', 'password', 'password_repeat'],
        ];
    }

    public function rules(): array
    {
        return [
            [['token', 'phone', 'code', 'password', 'password_repeat'], 'required'],
            ['password', 'string', 'min' => (int)(Yii::$app->params['auth.passwordMinLength'] ?? 8), 'max' => 72],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => 'Пароли не совпадают.'],
            ['code', 'match', 'pattern' => '/^\d{4,8}$/'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'phone' => 'Телефон',
            'code' => 'Код из SMS',
            'password' => 'Новый пароль',
            'password_repeat' => 'Повторите пароль',
        ];
    }
}
