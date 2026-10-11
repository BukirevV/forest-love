<?php

namespace app\forms;

use yii\base\Model;

/**
 * Подтверждение email по токену из письма.
 */
class EmailConfirmForm extends Model
{
    public ?string $token = null;

    public function rules(): array
    {
        return [
            ['token', 'required', 'message' => 'Ссылка подтверждения некорректна.'],
            ['token', 'string', 'min' => 16, 'max' => 128],
            ['token', 'match', 'pattern' => '/^[A-Za-z0-9_\-]+$/'],
        ];
    }
}
