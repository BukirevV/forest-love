<?php

namespace app\enums;

/**
 * Статус платежа (ТЗ 4.5)
 */
enum PaymentStatus: string
{
    use EnumLabelsTrait;

    case New = 'new';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Создан',
            self::Pending => 'Ожидает оплаты',
            self::Succeeded => 'Оплачен',
            self::Failed => 'Ошибка оплаты',
            self::Canceled => 'Отменён',
            self::Refunded => 'Возвращён',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Canceled => 'dark',
            self::Refunded => 'info',
        };
    }
}
