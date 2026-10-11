<?php

namespace app\enums;

/**
 * Действие модератора
 */
enum ModerationAction: string
{
    use EnumLabelsTrait;

    case Approve = 'approve';
    case Reject = 'reject';
    case Hide = 'hide';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Одобрено',
            self::Reject => 'Отклонено',
            self::Hide => 'Скрыто',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Approve => 'success',
            self::Reject => 'danger',
            self::Hide => 'dark',
        };
    }
}
