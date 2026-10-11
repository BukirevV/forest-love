<?php

namespace app\enums;

/**
 * Тип платежа
 */
enum PaymentType: string
{
    use EnumLabelsTrait;

    case NamedTree = 'named_tree';

    public function label(): string
    {
        return match ($this) {
            self::NamedTree => 'Именное дерево',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::NamedTree => 'primary',
        };
    }
}
