<?php

namespace app\services;

use Yii;

/**
 * Извлечение из фото даты съёмки, GPS-координат (DMS → десятичные градусы с учётом полушарий) и ориентации.
 */
class ExifService
{
    /**
     * @return array{date: ?string, latitude: ?float, longitude: ?float, orientation: int, datetime: ?string}
     */
    public function extract(string $path): array
    {
        $result = ['date' => null, 'datetime' => null, 'latitude' => null, 'longitude' => null, 'orientation' => 1];

        if (!is_file($path) || !function_exists('exif_read_data')) {
            return $result;
        }
        $exif = @exif_read_data($path, 'ANY_TAG', true);
        if (!is_array($exif)) {
            return $result;
        }

        $result['orientation'] = (int)($exif['IFD0']['Orientation'] ?? 1);

        $rawDate = $exif['EXIF']['DateTimeOriginal'] ?? $exif['EXIF']['DateTimeDigitized'] ?? $exif['IFD0']['DateTime'] ?? null;
        if (is_string($rawDate)) {
            $parsed = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', trim($rawDate));
            if ($parsed && (int)$parsed->format('Y') > 1990 && $parsed <= new \DateTimeImmutable('+1 day')) {
                $result['date'] = $parsed->format('Y-m-d');
                $result['datetime'] = $parsed->format('Y-m-d H:i:s');
            }
        }

        $gps = $exif['GPS'] ?? [];
        if (isset($gps['GPSLatitude'], $gps['GPSLongitude'])) {
            $lat = $this->dmsToDecimal($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N');
            $lng = $this->dmsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');
            if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                $result['latitude'] = round($lat, 7);
                $result['longitude'] = round($lng, 7);
            }
        }

        return $result;
    }

    /**
     * Градусы/минуты/секунды (рациональные числа EXIF) → десятичные градусы.
     * Южное (S) и западное (W) полушария — отрицательные значения.
     */
    public function dmsToDecimal(array|string $dms, string $ref): ?float
    {
        if (is_string($dms)) {
            $dms = preg_split('/[,\s]+/', trim($dms));
        }
        $dms = array_values($dms);
        if (count($dms) < 1) {
            return null;
        }
        $deg = $this->rational($dms[0] ?? '0');
        $min = $this->rational($dms[1] ?? '0');
        $sec = $this->rational($dms[2] ?? '0');
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }
        $value = $deg + $min / 60 + $sec / 3600;
        $ref = strtoupper(trim($ref));
        return in_array($ref, ['S', 'W'], true) ? -$value : $value;
    }

    private function rational(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }
        $value = trim((string)$value);
        if (str_contains($value, '/')) {
            [$num, $den] = array_map('floatval', explode('/', $value, 2));
            return $den == 0.0 ? null : $num / $den;
        }
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Удобный вывод для JSON-ответа (planting/exif).
     */
    public function toClientArray(array $exif): array
    {
        return [
            'date' => $exif['date'],
            'latitude' => $exif['latitude'],
            'longitude' => $exif['longitude'],
            'hasCoords' => $exif['latitude'] !== null,
        ];
    }
}
