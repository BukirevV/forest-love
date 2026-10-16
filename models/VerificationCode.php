<?php

namespace app\models;

use app\enums\VerificationChannel;
use app\enums\VerificationPurpose;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Код (SMS) или токен (email) подтверждения (ТЗ 6.2).
 * Открытое значение хранится только в виде HMAC-хэша.
 *
 * @property int $id
 * @property int $user_id
 * @property string $channel
 * @property string $purpose
 * @property string $target
 * @property string $code_hash
 * @property int $attempts
 * @property int $expires_at
 * @property int|null $used_at
 * @property string|null $ip
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read User $user
 */
class VerificationCode extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%verification_code}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'channel', 'purpose', 'target', 'code_hash', 'expires_at'], 'required'],
            [['user_id', 'attempts', 'expires_at', 'used_at'], 'integer'],
            ['channel', 'in', 'range' => VerificationChannel::values()],
            ['purpose', 'in', 'range' => VerificationPurpose::values()],
            [['target', 'code_hash'], 'string', 'max' => 255],
            ['ip', 'string', 'max' => 45],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'user_id' => 'Пользователь',
            'channel' => 'Канал',
            'purpose' => 'Назначение',
            'target' => 'Адресат',
            'attempts' => 'Попыток',
            'expires_at' => 'Действует до',
            'used_at' => 'Использован',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Создаёт код подтверждения.
     * Для SMS — цифровой код длиной auth.codeLength; для email — длинный токен для ссылки.
     *
     * @return array{0: self, 1: string} [модель, открытый код]
     */
    public static function create(User $user, string $channel, string $purpose, string $target, ?int $ttl = null): array
    {
        $params = Yii::$app->params;
        if ($channel === VerificationChannel::Sms->value) {
            $length = (int)($params['auth.codeLength'] ?? 6);
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= (string)random_int(0, 9);
            }
            $ttl ??= (int)($params['auth.codeTtl'] ?? 600);
        } else {
            $code = Yii::$app->security->generateRandomString(48);
            $ttl ??= $purpose === VerificationPurpose::PasswordReset->value
                ? (int)($params['auth.passwordResetTokenTtl'] ?? 3600)
                : (int)($params['auth.emailTokenTtl'] ?? 86400);
        }

        $model = new self([
            'user_id' => $user->id,
            'channel' => $channel,
            'purpose' => $purpose,
            'target' => $target,
            'code_hash' => self::hashCode($code),
            'attempts' => 0,
            'expires_at' => time() + $ttl,
            'ip' => Yii::$app->request instanceof \yii\web\Request ? Yii::$app->request->userIP : null,
        ]);
        $model->save(false);

        return [$model, $code];
    }

    /**
     * Детерминированный хэш (нужен для поиска email-токена по значению).
     */
    public static function hashCode(string $code): string
    {
        $secret = $_ENV['COOKIE_VALIDATION_KEY', 'forest-love') . '|verification';
        return hash_hmac('sha256', $code, (string)$secret);
    }

    /**
     * Поиск действующего email-токена.
     */
    public static function findByToken(string $token, string $purpose): ?self
    {
        return self::find()
            ->where([
                'code_hash' => self::hashCode($token),
                'purpose' => $purpose,
                'channel' => VerificationChannel::Email->value,
                'used_at' => null,
            ])
            ->andWhere(['>', 'expires_at', time()])
            ->one();
    }

    /**
     * Последний действующий код для адресата и назначения.
     */
    public static function findActive(string $target, string $purpose): ?self
    {
        return self::find()
            ->where(['target' => $target, 'purpose' => $purpose, 'used_at' => null])
            ->andWhere(['>', 'expires_at', time()])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= time();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->attempts >= (int)(Yii::$app->params['auth.codeMaxAttempts'] ?? 5);
    }

    /**
     * Проверяет код. Неверный ввод увеличивает счётчик попыток;
     * после N неверных попыток код блокируется.
     */
    public function verify(string $code): bool
    {
        if ($this->isUsed() || $this->isExpired() || $this->isLocked()) {
            return false;
        }
        if (hash_equals($this->code_hash, self::hashCode(trim($code)))) {
            $this->used_at = time();
            $this->save(false, ['used_at', 'updated_at']);
            return true;
        }
        $this->updateCounters(['attempts' => 1]);
        return false;
    }

    public function markUsed(): void
    {
        $this->used_at = time();
        $this->save(false, ['used_at', 'updated_at']);
    }
}
