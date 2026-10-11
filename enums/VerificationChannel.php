<?php

namespace app\enums;

/**
 * Канал отправки кода подтверждения
 */
enum VerificationChannel: string
{
    use EnumLabelsTrait;

    case Email = 'email';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Email => 'primary',
            self::Sms => 'info',
        };
    }
}
