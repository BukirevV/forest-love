<?php

namespace app\models;

use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\models\query\PaymentQuery;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * Платёж (ТЗ 6.9).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $planting_id
 * @property string $type
 * @property string|float $amount
 * @property string $currency
 * @property string $status
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property string $idempotency_key
 * @property array|string|null $payload
 * @property array|string|null $meta  данные заказа (например, имя дерева)
 * @property int|null $paid_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read User $user
 * @property-read Planting|null $planting
 */
class Payment extends ActiveRecord
{
    private const FINAL_STATUSES = [
        PaymentStatus::Succeeded->value,
        PaymentStatus::Failed->value,
        PaymentStatus::Canceled->value,
        PaymentStatus::Refunded->value,
    ];

    public static function tableName(): string
    {
        return '{{%payment}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type', 'amount', 'currency', 'provider', 'idempotency_key'], 'required'],
            [['user_id', 'planting_id', 'paid_at'], 'integer'],
            ['type', 'in', 'range' => PaymentType::values()],
            ['amount', 'number', 'min' => 0],
            ['currency', 'string', 'length' => 3],
            ['status', 'default', 'value' => PaymentStatus::New->value],
            ['status', 'in', 'range' => PaymentStatus::values()],
            ['provider', 'string', 'max' => 30],
            ['provider_payment_id', 'string', 'max' => 100],
            ['provider_payment_id', 'unique', 'skipOnEmpty' => true],
            ['idempotency_key', 'string', 'max' => 64],
            ['idempotency_key', 'unique'],
            [['payload', 'meta'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => '№',
            'user_id' => 'Пользователь',
            'planting_id' => 'Карточка',
            'type' => 'Назначение',
            'amount' => 'Сумма',
            'currency' => 'Валюта',
            'status' => 'Статус',
            'provider' => 'Провайдер',
            'provider_payment_id' => 'ID у провайдера',
            'idempotency_key' => 'Ключ идемпотентности',
            'payload' => 'Ответ провайдера',
            'paid_at' => 'Оплачен',
            'created_at' => 'Создан',
            'updated_at' => 'Изменён',
        ];
    }

    public static function find(): PaymentQuery
    {
        return new PaymentQuery(static::class);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getPlanting(): ActiveQuery
    {
        return $this->hasOne(Planting::class, ['id' => 'planting_id']);
    }

    public function getStatusEnum(): PaymentStatus
    {
        return PaymentStatus::from($this->status);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded->value;
    }

    public function getMeta(): array
    {
        $meta = $this->getAttribute('meta');
        if (is_string($meta)) {
            $meta = Json::decode($meta);
        }
        return is_array($meta) ? $meta : [];
    }

    public function getPayloadArray(): array
    {
        $payload = $this->getAttribute('payload');
        if (is_string($payload)) {
            $payload = Json::decode($payload);
        }
        return is_array($payload) ? $payload : [];
    }

    /**
     * Перевод в succeeded. Идемпотентно: при повторном вызове возвращает false
     * и не создаёт повторных эффектов.
     *
     * @return bool true — статус действительно изменён сейчас
     */
    public function markSucceeded(?array $payload = null): bool
    {
        if ($this->isSucceeded()) {
            return false;
        }
        if ($this->isFinal()) {
            throw new \LogicException("Платёж #{$this->id} уже в финальном статусе {$this->status}.");
        }
        $this->status = PaymentStatus::Succeeded->value;
        $this->paid_at = time();
        if ($payload !== null) {
            $this->payload = $payload;
        }
        return $this->save(false);
    }

    public function markFailed(?array $payload = null, PaymentStatus $status = PaymentStatus::Failed): bool
    {
        if ($this->isFinal()) {
            return false;
        }
        $this->status = $status->value;
        if ($payload !== null) {
            $this->payload = $payload;
        }
        return $this->save(false);
    }

    public function markPending(string $providerPaymentId, ?array $payload = null): bool
    {
        if ($this->isFinal()) {
            return false;
        }
        $this->status = PaymentStatus::Pending->value;
        $this->provider_payment_id = $providerPaymentId;
        if ($payload !== null) {
            $this->payload = $payload;
        }
        return $this->save(false);
    }
}
