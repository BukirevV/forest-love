<?php

namespace app\forms;

use app\enums\UserStatus;
use app\models\User;
use app\validators\PhoneValidator;
use Yii;
use yii\base\Model;

/**
 * Создание и редактирование пользователя администратором.
 * Роли и прямые разрешения сохраняет AccessService (с защитой от потери доступа).
 */
class UserAdminForm extends Model
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_UPDATE = 'update';

    public ?string $email = null;
    public ?string $phone = null;
    public ?string $display_name = null;
    public ?string $status = null;
    public ?string $password = null;
    public bool|string $email_verified = false;
    public bool|string $phone_verified = false;
    /** @var string[]|string */
    public array|string|null $roles = [];
    /** @var string[]|string */
    public array|string|null $permissions = [];

    public function __construct(private readonly User $user, array $config = [])
    {
        if (!$user->isNewRecord) {
            $this->email = $user->email;
            $this->phone = $user->phone;
            $this->display_name = $user->display_name;
            $this->status = $user->status;
            $this->email_verified = $user->isEmailVerified();
            $this->phone_verified = $user->isPhoneVerified();
        } else {
            $this->status = UserStatus::Active->value;
            $this->email_verified = true;
            $this->phone_verified = true;
        }
        parent::__construct($config);
        $this->scenario = $user->isNewRecord ? self::SCENARIO_CREATE : self::SCENARIO_UPDATE;
    }

    public function scenarios(): array
    {
        $all = ['email', 'phone', 'display_name', 'status', 'password', 'email_verified', 'phone_verified', 'roles', 'permissions'];
        return [self::SCENARIO_CREATE => $all, self::SCENARIO_UPDATE => $all, self::SCENARIO_DEFAULT => $all];
    }

    public function rules(): array
    {
        $exceptSelf = $this->user->isNewRecord ? null : ['<>', 'id', $this->user->id];
        return [
            [['email', 'phone', 'display_name', 'password'], 'trim'],
            [['email', 'phone', 'display_name', 'password'], 'default', 'value' => null],
            ['email', 'filter', 'filter' => 'mb_strtolower', 'skipOnEmpty' => true],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => User::class, 'filter' => $exceptSelf, 'message' => 'Этот email уже используется.'],
            ['phone', PhoneValidator::class],
            ['phone', 'unique', 'targetClass' => User::class, 'filter' => $exceptSelf, 'message' => 'Этот телефон уже используется.'],
            ['email', 'validateContact', 'skipOnEmpty' => false],
            ['display_name', 'string', 'max' => 100],
            ['status', 'required'],
            ['status', 'in', 'range' => UserStatus::values()],
            ['password', 'required', 'on' => self::SCENARIO_CREATE],
            ['password', 'string', 'min' => (int)(Yii::$app->params['auth.passwordMinLength'] ?? 8), 'max' => 72],
            [['email_verified', 'phone_verified'], 'boolean'],
            [['roles', 'permissions'], 'default', 'value' => []],
            [['roles', 'permissions'], 'each', 'rule' => ['string']],
        ];
    }

    public function validateContact(): void
    {
        if (empty($this->email) && empty($this->phone)) {
            $this->addError('email', 'Укажите email или телефон.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'Email',
            'phone' => 'Телефон',
            'display_name' => 'Имя',
            'status' => 'Статус',
            'password' => $this->user->isNewRecord ? 'Пароль' : 'Новый пароль',
            'email_verified' => 'Email подтверждён',
            'phone_verified' => 'Телефон подтверждён',
            'roles' => 'Роли',
            'permissions' => 'Дополнительные разрешения (помимо ролей)',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'password' => $this->user->isNewRecord ? '' : 'Оставьте пустым, чтобы не менять. При смене пароля пользователь будет разлогинен на всех устройствах.',
            'permissions' => 'Обычно достаточно ролей. Отдельные разрешения нужны для точечных исключений.',
        ];
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Сохраняет данные учётной записи (без прав доступа).
     */
    public function saveUser(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $u = $this->user;
        $u->email = $this->email;
        $u->phone = $this->phone;
        $u->display_name = $this->display_name;

        $statusChanged = $u->status !== $this->status;
        $u->status = $this->status;

        $u->email_verified_at = $this->email && $this->email_verified ? ($u->email_verified_at ?: time()) : null;
        $u->phone_verified_at = $this->phone && $this->phone_verified ? ($u->phone_verified_at ?: time()) : null;

        if ($this->password) {
            $u->setPassword($this->password);
            $u->generateAuthKey();
        } elseif ($u->isNewRecord) {
            $u->generateAuthKey();
        }
        if ($statusChanged && $this->status === UserStatus::Blocked->value) {
            $u->generateAuthKey(); // сброс «запомнить меня»
        }

        if (!$u->save()) {
            $this->addErrors($u->getErrors());
            return false;
        }
        return true;
    }

    /** @return string[] */
    public function getRoleList(): array
    {
        return array_values(array_filter((array)$this->roles, 'strlen'));
    }

    /** @return string[] */
    public function getPermissionList(): array
    {
        return array_values(array_filter((array)$this->permissions, 'strlen'));
    }
}
