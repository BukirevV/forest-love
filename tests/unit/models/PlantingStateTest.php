<?php

namespace tests\unit\models;

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\models\Planting;
use app\models\User;
use Codeception\Test\Unit;

/**
 * Переходы и вычисляемые признаки карточки (без БД).
 */
class PlantingStateTest extends Unit
{
    private function planting(array $attrs = []): Planting
    {
        $p = new Planting();
        $p->setAttributes(array_merge([
            'user_id' => 1, 'species_id' => 1, 'latitude' => 55.75, 'longitude' => 37.62,
            'status' => PlantingStatus::Draft->value, 'named_status' => NamedStatus::None->value,
        ], $attrs), false);
        return $p;
    }

    public function testIsNamed(): void
    {
        $this->assertFalse($this->planting()->isNamed());
        $this->assertTrue($this->planting(['named_status' => 'active'])->isNamed());
        $this->assertTrue($this->planting(['named_status' => 'active', 'named_until' => time() + 100])->isNamed());
        $this->assertFalse($this->planting(['named_status' => 'active', 'named_until' => time() - 100])->isNamed());
        $this->assertFalse($this->planting(['named_status' => 'awaiting_payment'])->isNamed());
    }

    public function testPublished(): void
    {
        $this->assertFalse($this->planting()->isPublished());
        $this->assertTrue($this->planting(['status' => 'approved'])->isPublished());
    }

    public function testCanBeEditedBy(): void
    {
        $owner = new User(['id' => 1]);
        $other = new User(['id' => 2]);
        $p = $this->planting();
        $this->assertTrue($p->canBeEditedBy($owner));
        $this->assertFalse($p->canBeEditedBy($other));
        $this->assertFalse($p->canBeEditedBy(null));
        $this->assertFalse($this->planting(['status' => 'hidden'])->canBeEditedBy($owner));
    }

    public function testCanOrderNamed(): void
    {
        $this->assertTrue($this->planting()->canOrderNamed());
        $this->assertTrue($this->planting(['status' => 'pending'])->canOrderNamed());
        $this->assertFalse($this->planting(['named_status' => 'active'])->canOrderNamed());
        $this->assertFalse($this->planting(['status' => 'hidden'])->canOrderNamed());
    }

    public function testToMapPoint(): void
    {
        $p = $this->planting(['named_status' => 'active']);
        $p->id = 7;
        $point = $p->toMapPoint();
        $this->assertSame('Feature', $point['type']);
        $this->assertSame([37.62, 55.75], $point['geometry']['coordinates']);
        $this->assertTrue($point['properties']['named']);
    }
}
