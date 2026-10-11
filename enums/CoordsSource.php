<?php

namespace app\enums;

/**
 * Источник координат посадки
 */
enum CoordsSource: string
{
    use EnumLabelsTrait;

    case Exif = 'exif';
    case Manual = 'manual';
    case Device = 'device';

    public function label(): string
    {
        return match ($this) {
            self::Exif => 'Из фото (EXIF)',
            self::Manual => 'Указаны вручную',
            self::Device => 'Геолокация устройства',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Exif => 'info',
            self::Manual => 'secondary',
            self::Device => 'primary',
        };
    }
}
