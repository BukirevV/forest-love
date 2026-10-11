<?php

namespace app\enums;

/**
 * Статус ревизии карточки (ТЗ 5.7)
 */
enum RevisionStatus: string
{
    use EnumLabelsTrait;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'На модерации',
            self::Approved => 'Одобрена',
            self::Rejected => 'Отклонена',
            self::Superseded => 'Заменена новой правкой',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Superseded => 'secondary',
        };
    }
}
