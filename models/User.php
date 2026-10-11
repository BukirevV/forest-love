<?php

namespace app\models;

use app\enums\UserStatus;
use app\models\query\UserQuery;
use app\validators\PhoneValidator;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Пользователь (ТЗ 6.1).
 *
 * @property int $id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password_hash
 * @property string $auth_key
 * @property string|null $display_name
 * @property string $status
 * @property int|null $email_verified_at
 * @property int|null $phone_verified_at
 * @property int|null $last_login_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Planting[] $plantings
 * @property-read Payment[] $payments
 * @property-read string $publicName
 * @property-read string[] $roles
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const SCENARIO_ADMIN = 'admin';

    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['email', 'phone', 'display_name'], 'trim'],
            [['email', 'phone', 'display_name'], 'default', 'value' => null],
            ['email', 'filter', 'filter' => static fn($v) => $v === null ? null : mb_strtolower($v), 'skipOnEmpty' => true],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'message' => 'Этот email уже зарегистрирован.'],
            ['phone', PhoneValidator::class],
            ['phone', 'unique', 'message' => 'Этот телефон уже зарегистрирован.'],
            ['display_name', 'string', 'max' => 100],
            [['email', 'phone'], 'validateContactPresence', 'skipOnEmpty' => false],
            ['status', 'default', 'value' => UserStatus::Unverified->value],
            ['status', 'in', 'range' => UserStatus::values()],
            [['email_verified_at', 'phone_verified_at', 'last_login_at'], 'integer'],
        ];
    }

    public function validateContactPresence(string $attribute): void
    {
        if (empty($this->email) && empty($this->phone) && $attribute === 'email') {
            $this->addError('email', 'Необходимо указать email или телефон.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'email' => 'Email',
            'phone' => 'Телефон',
            'password_hash' => 'Хэш пароля',
            'auth_key' => 'Ключ авторизации',
            'display_name' => 'Имя',
            'status' => 'Статус',
            'email_verified_at' => 'Email подтверждён',
            'phone_verified_at' => 'Телефон подтверждён',
            'last_login_at' => 'Последний вход',
            'created_at' => 'Зарегистрирован',
            'updated_at' => 'Обновлён',
        ];
    }

    public static function find(): UserQuery
    {
        return new UserQuery(static::class);
    }

    public function beforeValidate(): bool
    {
        if (!empty($this->phone)) {
            $normalized = PhoneValidator::normalize((string)$this->phone);
            if ($normalized !== null) {
                $this->phone = $normalized;
            }
        }
        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if ($insert && empty($this->auth_key)) {
            $this->generateAuthKey();
        }
        return parent::beforeSave($insert);
    }

    // ---- IdentityInterface ----

    public static function findIdentity($id): ?static
    {
        return static::find()->andWhere(['id' => $id, 'status' => UserStatus::Active->value])->one();
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('Авторизация по токену не поддерживается.');
    }

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $authKey !== null && hash_equals($this->auth_key, (string)$authKey);
    }

    // ---- Поиск ----

    public static function findByEmail(string $email): ?static
    {
        return static::findOne(['email' => mb_strtolower(trim($email))]);
    }

    public static function findByPhone(string $phone): ?static
    {
        $normalized = PhoneValidator::normalize($phone);
        return $normalized ? static::findOne(['phone' => $normalized]) : null;
    }

    /**
     * Определяет, email это или телефон, и ищет пользователя.
     */
    public static function findByLogin(string $login): ?static
    {
        $login = trim($login);
        return str_contains($login, '@') ? static::findByEmail($login) : static::findByPhone($login);
    }

    // ---- Пароль и ключи ----

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(string $password): bool
    {
        return $this->password_hash !== null
            && Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }

    // ---- Статусы ----

    public function getStatusEnum(): UserStatus
    {
        return UserStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active->value;
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked->value;
    }

    public function markEmailVerified(): void
    {
        $this->email_verified_at = time();
        if ($this->status === UserStatus::Unverified->value) {
            $this->status = UserStatus::Active->value;
        }
    }

    public function markPhoneVerified(): void
    {
        $this->phone_verified_at = time();
        if ($this->status === UserStatus::Unverified->value) {
            $this->status = UserStatus::Active->value;
        }
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Имя для интерфейса. Email и телефон публично не показываются (ТЗ 19).
     */
    public function getPublicName(): string
    {
        return $this->display_name ?: 'Участник #' . $this->id;
    }

    /**
     * Имя для кабинета/админки (с контактами).
     */
    public function getPrivateName(): string
    {
        return $this->display_name ?: ($this->email ?: (string)$this->phone);
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return array_keys(Yii::$app->authManager->getRolesByUser($this->id));
    }

    // ---- Связи ----

    public function getPlantings(): ActiveQuery
    {
        return $this->hasMany(Planting::class, ['user_id' => 'id'])->inverseOf('user');
    }

    public function getPayments(): ActiveQuery
    {
        return $this->hasMany(Payment::class, ['user_id' => 'id']);
    }
}
