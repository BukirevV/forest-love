<?php

namespace app\enums;

/**
 * Тип рекламного размещения
 */
enum PlacementType: string
{
    use EnumLabelsTrait;

    case Point = 'point';
    case Banner = 'banner';

    public function label(): string
    {
        return match ($this) {
            self::Point => 'Точка на карте',
            self::Banner => 'Баннер',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Point => 'primary',
            self::Banner => 'info',
        };
    }
}
