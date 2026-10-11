<?php

namespace app\forms;

use app\models\Planting;
use app\models\Setting;
use yii\base\Model;

/**
 * Заказ именного размещения (создаёт платёж).
 */
class NamedOrderForm extends Model
{
    public int|string|null $planting_id = null;
    public ?string $tariff = 'default';
    public ?string $title = null;
    public bool|string|null $agree = null;

    public function rules(): array
    {
        return [
            [['planting_id', 'title'], 'required'],
            ['planting_id', 'integer'],
            ['planting_id', 'exist', 'targetClass' => Planting::class, 'targetAttribute' => 'id'],
            ['title', 'trim'],
            ['title', 'string', 'min' => 2, 'max' => 150],
            ['tariff', 'in', 'range' => array_keys(self::tariffs())],
            ['agree', 'required', 'requiredValue' => 1, 'message' => 'Необходимо принять условия размещения.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'planting_id' => 'Карточка',
            'tariff' => 'Тариф',
            'title' => 'Имя дерева / посвящение',
            'agree' => 'Я принимаю условия именного размещения',
        ];
    }

    /**
     * Тарифы. Пока один тариф из настроек; расширяемо (ТЗ 22, вопрос 1).
     *
     * @return array<string, array{label: string, price: float, currency: string, days: ?int}>
     */
    public static function tariffs(): array
    {
        $days = Setting::get('named.durationDays');
        return [
            'default' => [
                'label' => $days ? "Именное дерево на {$days} дн." : 'Именное дерево (бессрочно)',
                'price' => (float)Setting::get('named.price', 0),
                'currency' => (string)Setting::get('named.currency', 'RUB'),
                'days' => $days ? (int)$days : null,
            ],
        ];
    }

    public function getTariff(): array
    {
        return self::tariffs()[$this->tariff ?? 'default'];
    }
}
