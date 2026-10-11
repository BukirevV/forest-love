<?php

namespace tests\unit\validators;

use app\validators\CoordinateValidator;
use Codeception\Test\Unit;
use yii\base\DynamicModel;

class CoordinateValidatorTest extends Unit
{
    private function model(mixed $lat, mixed $lng): DynamicModel
    {
        $m = new DynamicModel(['latitude' => $lat, 'longitude' => $lng]);
        $m->addRule('latitude', CoordinateValidator::class, ['type' => 'latitude', 'pairAttribute' => 'longitude']);
        $m->addRule('longitude', CoordinateValidator::class, ['type' => 'longitude', 'pairAttribute' => 'latitude']);
        return $m;
    }

    public function testValidCoordinates(): void
    {
        $m = $this->model('55.7558', 37.6173);
        $this->assertTrue($m->validate());
        $this->assertSame(55.7558, $m->latitude);
    }

    public function testBoundaries(): void
    {
        $this->assertTrue($this->model(90, 180)->validate());
        $this->assertTrue($this->model(-90, -180)->validate());
        $this->assertFalse($this->model(90.0001, 10)->validate());
        $this->assertFalse($this->model(10, -180.5)->validate());
    }

    public function testZeroPointRejected(): void
    {
        $m = $this->model(0, 0);
        $this->assertFalse($m->validate());
        $this->assertTrue($m->hasErrors('latitude'));
    }

    public function testNonNumericRejected(): void
    {
        $this->assertFalse($this->model('abc', 10)->validate());
    }

    public function testIsValidPair(): void
    {
        $this->assertTrue(CoordinateValidator::isValidPair(55, 37));
        $this->assertFalse(CoordinateValidator::isValidPair(0, 0));
        $this->assertFalse(CoordinateValidator::isValidPair(null, 37));
    }
}
