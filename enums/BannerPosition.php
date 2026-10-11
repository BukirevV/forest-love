<?php

namespace app\enums;

/**
 * Позиция баннера поверх карты
 */
enum BannerPosition: string
{
    use EnumLabelsTrait;

    case Top = 'top';
    case Bottom = 'bottom';
    case TopLeft = 'top-left';
    case TopRight = 'top-right';
    case BottomLeft = 'bottom-left';
    case BottomRight = 'bottom-right';

    public function label(): string
    {
        return match ($this) {
            self::Top => 'Сверху',
            self::Bottom => 'Снизу',
            self::TopLeft => 'Сверху слева',
            self::TopRight => 'Сверху справа',
            self::BottomLeft => 'Снизу слева',
            self::BottomRight => 'Снизу справа',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Top => 'secondary',
            self::Bottom => 'secondary',
            self::TopLeft => 'secondary',
            self::TopRight => 'secondary',
            self::BottomLeft => 'secondary',
            self::BottomRight => 'secondary',
        };
    }
}
