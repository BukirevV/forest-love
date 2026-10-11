<?php

namespace app\enums;

/**
 * Статус пользователя (ТЗ 4.1)
 */
enum UserStatus: string
{
    use EnumLabelsTrait;

    case Unverified = 'unverified';
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Не подтверждён',
            self::Active => 'Активен',
            self::Blocked => 'Заблокирован',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Unverified => 'warning',
            self::Active => 'success',
            self::Blocked => 'danger',
        };
    }
}
