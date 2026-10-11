<?php

namespace app\enums;

/**
 * Статус карточки посадки (ТЗ 4.2)
 */
enum PlantingStatus: string
{
    use EnumLabelsTrait;

    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Pending => 'На модерации',
            self::Approved => 'Опубликована',
            self::Rejected => 'Отклонена',
            self::Hidden => 'Скрыта',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Hidden => 'dark',
        };
    }
}
