<?php

namespace app\forms;

use app\validators\PhoneValidator;
use Yii;
use yii\base\Model;

/**
 * Вход по одноразовому SMS-коду без пароля (опционально).
 * Сценарий request — запрос кода, verify — ввод кода.
 */
class SmsLoginForm extends Model
{
    public const SCENARIO_REQUEST = 'request';
    public const SCENARIO_VERIFY = 'verify';

    public ?string $phone = null;
    public ?string $code = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_REQUEST => ['phone'],
            self::SCENARIO_VERIFY => ['phone', 'code'],
        ];
    }

    public function rules(): array
    {
        $length = (int)(Yii::$app->params['auth.codeLength'] ?? 6);
        return [
            [['phone', 'code'], 'required'],
            ['phone', PhoneValidator::class],
            ['code', 'match', 'pattern' => '/^\d{' . $length . '}$/'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['phone' => 'Телефон', 'code' => 'Код из SMS'];
    }
}
