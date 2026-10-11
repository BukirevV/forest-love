<?php

namespace app\enums;

/**
 * Общие методы для enum-статусов: подписи, списки для выпадающих списков и валидации.
 */
trait EnumLabelsTrait
{
    abstract public function label(): string;

    /** @return array<string, string> [value => label] */
    public static function labels(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }

    public static function labelOf(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $case = self::tryFrom($value);
        return $case ? $case->label() : $value;
    }

    /** CSS-класс Bootstrap для бейджа. */
    public function cssClass(): string
    {
        return 'secondary';
    }
}
