<?php

namespace app\enums;

/**
 * Статус рекламной кампании (ТЗ 4.6)
 */
enum CampaignStatus: string
{
    use EnumLabelsTrait;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Active => 'Активна',
            self::Paused => 'Приостановлена',
            self::Finished => 'Завершена',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Finished => 'dark',
        };
    }
}
