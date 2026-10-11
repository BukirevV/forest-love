<?php

namespace tests\unit\services;

use app\services\ExifService;
use Codeception\Test\Unit;

/**
 * Для полной проверки положите в tests/_data/exif/ фото с GPS (with-gps.jpg) и без (no-gps.jpg).
 */
class ExifServiceTest extends Unit
{
    private ExifService $service;

    protected function _before(): void
    {
        $this->service = new ExifService();
    }

    public function testDmsToDecimalNorthEast(): void
    {
        // 55°45'20.88" N
        $this->assertEqualsWithDelta(55.7558, $this->service->dmsToDecimal(['55/1', '45/1', '2088/100'], 'N'), 0.0001);
    }

    public function testDmsToDecimalSouthWest(): void
    {
        // 22°54'30" S, 43°10'45" W (Рио-де-Жанейро)
        $this->assertEqualsWithDelta(-22.9083, $this->service->dmsToDecimal(['22/1', '54/1', '30/1'], 'S'), 0.0001);
        $this->assertEqualsWithDelta(-43.1792, $this->service->dmsToDecimal(['43/1', '10/1', '45/1'], 'W'), 0.0001);
    }

    public function testDmsWithZeroDenominator(): void
    {
        $this->assertNull($this->service->dmsToDecimal(['55/0', '0/1', '0/1'], 'N'));
    }

    public function testMissingFile(): void
    {
        $r = $this->service->extract('/nonexistent.jpg');
        $this->assertNull($r['latitude']);
        $this->assertNull($r['date']);
        $this->assertSame(1, $r['orientation']);
    }

    public function testPhotoWithoutGps(): void
    {
        $file = codecept_data_dir('tree.jpg');
        if (!is_file($file)) {
            $this->markTestSkipped('Нет тестового изображения');
        }
        $r = $this->service->extract($file);
        $this->assertNull($r['latitude']);
        $this->assertNull($r['longitude']);
    }

    public function testPhotoWithGps(): void
    {
        $file = codecept_data_dir('exif/with-gps.jpg');
        if (!is_file($file)) {
            $this->markTestSkipped('Положите фото с GPS в tests/_data/exif/with-gps.jpg');
        }
        $r = $this->service->extract($file);
        $this->assertNotNull($r['latitude']);
        $this->assertNotNull($r['longitude']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string)$r['date']);
    }
}
