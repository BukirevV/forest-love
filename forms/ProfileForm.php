<?php

namespace app\forms;

use app\models\User;
use app\validators\PhoneValidator;
use yii\base\Model;

/**
 * Профиль. Смена email/телефона требует подтверждения нового контакта:
 * контакт меняется только после ввода кода / перехода по ссылке.
 */
class ProfileForm extends Model
{
    public ?string $display_name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $new_password = null;
    public ?string $current_password = null;

    public function __construct(private readonly User $user, array $config = [])
    {
        $this->display_name = $user->display_name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['display_name', 'email', 'phone'], 'trim'],
            [['display_name', 'email', 'phone'], 'default', 'value' => null],
            ['display_name', 'string', 'max' => 100],
            ['email', 'email'],
            ['email', 'filter', 'filter' => 'mb_strtolower', 'skipOnEmpty' => true],
            ['email', 'unique', 'targetClass' => User::class, 'filter' => ['<>', 'id', $this->user->id],
                'message' => 'Этот email уже используется.'],
            ['phone', PhoneValidator::class],
            ['phone', 'unique', 'targetClass' => User::class, 'filter' => ['<>', 'id', $this->user->id],
                'message' => 'Этот телефон уже используется.'],
            ['email', 'validateContacts', 'skipOnEmpty' => false],
            ['new_password', 'string', 'min' => 8, 'max' => 72],
            ['current_password', 'required', 'when' => fn() => !empty($this->new_password) && $this->user->password_hash,
                'message' => 'Для смены пароля укажите текущий пароль.'],
            ['current_password', 'validateCurrentPassword'],
        ];
    }

    public function validateContacts(): void
    {
        if (empty($this->email) && empty($this->phone)) {
            $this->addError('email', 'Должен остаться хотя бы один контакт.');
        }
        if (empty($this->email) && $this->user->email && !$this->user->isPhoneVerified()) {
            $this->addError('email', 'Нельзя удалить единственный подтверждённый контакт.');
        }
        if (empty($this->phone) && $this->user->phone && !$this->user->isEmailVerified()) {
            $this->addError('phone', 'Нельзя удалить единственный подтверждённый контакт.');
        }
    }

    public function validateCurrentPassword(string $attribute): void
    {
        if ($this->new_password && $this->user->password_hash && !$this->user->validatePassword((string)$this->$attribute)) {
            $this->addError($attribute, 'Текущий пароль указан неверно.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'display_name' => 'Имя (видно другим участникам)',
            'email' => 'Email',
            'phone' => 'Телефон',
            'new_password' => 'Новый пароль',
            'current_password' => 'Текущий пароль',
        ];
    }

    public function isEmailChanged(): bool
    {
        return $this->email !== null && $this->email !== $this->user->email;
    }

    public function isPhoneChanged(): bool
    {
        return $this->phone !== null && $this->phone !== $this->user->phone;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
