<?php

namespace app\validators;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Yii;
use yii\validators\Validator;

/**
 * Разбор и нормализация номера телефона к формату E.164 (+79991234567).
 * Использует giggsey/libphonenumber-for-php, при отсутствии — упрощённый разбор.
 */
class PhoneValidator extends Validator
{
    public ?string $defaultRegion = null;

    public function init(): void
    {
        parent::init();
        $this->message ??= 'Некорректный номер телефона.';
    }

    public function validateAttribute($model, $attribute): void
    {
        $normalized = self::normalize((string)$model->$attribute, $this->defaultRegion);
        if ($normalized === null) {
            $this->addError($model, $attribute, $this->message);
            return;
        }
        $model->$attribute = $normalized;
    }

    protected function validateValue($value): ?array
    {
        return self::normalize((string)$value, $this->defaultRegion) === null ? [$this->message, []] : null;
    }

    /**
     * Нормализует номер к E.164 или возвращает null, если номер некорректен.
     */
    public static function normalize(string $phone, ?string $region = null): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }
        $region ??= Yii::$app->params['auth.defaultRegion'] ?? 'RU';

        if (class_exists(PhoneNumberUtil::class)) {
            $util = PhoneNumberUtil::getInstance();
            try {
                // Российские номера, начинающиеся с 8, libphonenumber разбирает корректно для региона RU
                $number = $util->parse($phone, $region);
                if (!$util->isValidNumber($number)) {
                    return null;
                }
                return $util->format($number, PhoneNumberFormat::E164);
            } catch (NumberParseException) {
                return null;
            }
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($region === 'RU' && strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        if ($region === 'RU' && strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }
        return '+' . $digits;
    }

    /**
     * Похоже ли значение на телефон (а не на email).
     */
    public static function looksLikePhone(string $value): bool
    {
        $value = trim($value);
        return !str_contains($value, '@') && (bool)preg_match('/^\+?[\d\s\-()]{7,20}$/', $value);
    }

    /**
     * Маска для отображения: +7 *** ***-**-67
     */
    public static function mask(?string $phone): string
    {
        if (!$phone) {
            return '';
        }
        return substr($phone, 0, 2) . ' *** ***-**-' . substr($phone, -2);
    }
}
