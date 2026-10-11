<?php

namespace app\enums;

/**
 * Статус фотографии (ТЗ 4.3)
 */
enum PhotoStatus: string
{
    use EnumLabelsTrait;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'На модерации',
            self::Approved => 'Одобрена',
            self::Rejected => 'Отклонена',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
