<?php

namespace app\enums;

/**
 * Тип объекта модерации
 */
enum ModerationEntity: string
{
    use EnumLabelsTrait;

    case Planting = 'planting';
    case PlantingRevision = 'planting_revision';
    case PlantingPhoto = 'planting_photo';

    public function label(): string
    {
        return match ($this) {
            self::Planting => 'Карточка',
            self::PlantingRevision => 'Изменение карточки',
            self::PlantingPhoto => 'Фотография',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Planting => 'primary',
            self::PlantingRevision => 'info',
            self::PlantingPhoto => 'secondary',
        };
    }
}
