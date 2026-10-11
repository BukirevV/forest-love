<?php

namespace app\enums;

/**
 * Назначение кода подтверждения
 */
enum VerificationPurpose: string
{
    use EnumLabelsTrait;

    case Signup = 'signup';
    case Login = 'login';
    case PasswordReset = 'password_reset';
    case ContactChange = 'contact_change';

    public function label(): string
    {
        return match ($this) {
            self::Signup => 'Регистрация',
            self::Login => 'Вход',
            self::PasswordReset => 'Восстановление пароля',
            self::ContactChange => 'Смена контакта',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Signup => 'primary',
            self::Login => 'info',
            self::PasswordReset => 'warning',
            self::ContactChange => 'secondary',
        };
    }
}
