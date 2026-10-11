<?php

namespace app\enums;

/**
 * Статус именного размещения (ТЗ 4.4)
 */
enum NamedStatus: string
{
    use EnumLabelsTrait;

    case None = 'none';
    case AwaitingPayment = 'awaiting_payment';
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Не именное',
            self::AwaitingPayment => 'Ожидает оплаты',
            self::Active => 'Именное',
            self::Expired => 'Срок истёк',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::None => 'secondary',
            self::AwaitingPayment => 'warning',
            self::Active => 'success',
            self::Expired => 'dark',
        };
    }
}
