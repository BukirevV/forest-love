<?php

namespace app\validators;

use yii\validators\Validator;

/**
 * Проверка координат: широта −90…90, долгота −180…180, точка (0;0) запрещена.
 *
 * Использование:
 *   [['latitude'], CoordinateValidator::class, 'type' => 'latitude', 'pairAttribute' => 'longitude'],
 *   [['longitude'], CoordinateValidator::class, 'type' => 'longitude', 'pairAttribute' => 'latitude'],
 */
class CoordinateValidator extends Validator
{
    public const TYPE_LATITUDE = 'latitude';
    public const TYPE_LONGITUDE = 'longitude';

    /** latitude | longitude; если не задан — определяется по имени атрибута */
    public ?string $type = null;

    /** Парный атрибут для проверки точки (0;0) */
    public ?string $pairAttribute = null;

    public function init(): void
    {
        parent::init();
        $this->message ??= 'Некорректное значение «{attribute}».';
    }

    public function validateAttribute($model, $attribute): void
    {
        $value = $model->$attribute;
        $type = $this->type ?? (str_contains(strtolower($attribute), 'lon') || str_contains(strtolower($attribute), 'lng')
            ? self::TYPE_LONGITUDE : self::TYPE_LATITUDE);

        $pair = $this->pairAttribute !== null ? $model->{$this->pairAttribute} : null;
        $error = $this->check($value, $type, $pair);
        if ($error !== null) {
            $this->addError($model, $attribute, $error);
        } else {
            $model->$attribute = round((float)$value, 7);
        }
    }

    protected function validateValue($value): ?array
    {
        $error = $this->check($value, $this->type ?? self::TYPE_LATITUDE, null);
        return $error === null ? null : [$error, []];
    }

    private function check(mixed $value, string $type, mixed $pair): ?string
    {
        if (!is_numeric($value)) {
            return 'Значение «{attribute}» должно быть числом.';
        }
        $value = (float)$value;
        $limit = $type === self::TYPE_LONGITUDE ? 180.0 : 90.0;
        if ($value < -$limit || $value > $limit || is_nan($value)) {
            return $type === self::TYPE_LONGITUDE
                ? 'Долгота должна быть в диапазоне от −180 до 180.'
                : 'Широта должна быть в диапазоне от −90 до 90.';
        }
        if ($pair !== null && is_numeric($pair) && abs($value) < 1e-7 && abs((float)$pair) < 1e-7) {
            return 'Точка с координатами (0; 0) недопустима — отметьте место посадки на карте.';
        }
        return null;
    }

    public static function isValidPair(mixed $lat, mixed $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180
            && !(abs($lat) < 1e-7 && abs($lng) < 1e-7);
    }
}
