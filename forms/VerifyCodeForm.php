<?php

namespace app\forms;

use Yii;
use yii\base\Model;

/**
 * Подтверждение кода из SMS.
 */
class VerifyCodeForm extends Model
{
    public ?string $target = null;
    public ?string $code = null;

    public function rules(): array
    {
        $length = (int)(Yii::$app->params['auth.codeLength'] ?? 6);
        return [
            [['target', 'code'], 'required'],
            [['target', 'code'], 'trim'],
            ['code', 'match', 'pattern' => '/^\d{' . $length . '}$/', 'message' => "Код состоит из {$length} цифр."],
        ];
    }

    public function attributeLabels(): array
    {
        return ['target' => 'Телефон', 'code' => 'Код из SMS'];
    }
}
