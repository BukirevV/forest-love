<?php

namespace app\enums;

/**
 * Источник фотографии
 */
enum PhotoSource: string
{
    use EnumLabelsTrait;

    case Camera = 'camera';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Camera => 'Камера',
            self::Upload => 'Загрузка файла',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Camera => 'primary',
            self::Upload => 'secondary',
        };
    }
}
