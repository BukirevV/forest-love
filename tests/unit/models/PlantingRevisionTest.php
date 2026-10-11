<?php

namespace tests\unit\models;

use app\models\Planting;
use app\models\PlantingRevision;
use Codeception\Test\Unit;
use tests\fixtures\PlantingLinkFixture;
use tests\fixtures\PlantingPhotoFixture;

class PlantingRevisionTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'photos' => PlantingPhotoFixture::class,
            'links' => PlantingLinkFixture::class,
        ];
    }

    public function testApplyToReplacesFieldsAndLinks(): void
    {
        $planting = Planting::findOne(1);
        $revision = new PlantingRevision(['planting_id' => 1, 'user_id' => 1]);
        $revision->setData([
            'species_id' => 2,
            'latitude' => 56.0,
            'longitude' => 38.0,
            'planted_at' => '2026-04-01',
            'title' => 'Новое имя',
            'description' => null,
            'coords_source' => 'manual',
            'links' => [['url' => 'https://ok.ru/forest', 'label' => 'ОК']],
        ]);
        $revision->save(false);

        $this->assertArrayHasKey('species_id', $revision->getDiff());
        $this->assertArrayHasKey('links', $revision->getDiff());

        $revision->applyTo($planting);
        $planting->refresh();

        $this->assertSame(2, (int)$planting->species_id);
        $this->assertEqualsWithDelta(56.0, (float)$planting->latitude, 1e-7);
        $this->assertSame('Новое имя', $planting->title);
        $this->assertCount(1, $planting->links);
        $this->assertSame('ok', $planting->links[0]->network);
    }

    public function testDiffEmptyWhenNothingChanged(): void
    {
        $planting = Planting::findOne(1);
        $revision = new PlantingRevision(['planting_id' => 1, 'user_id' => 1]);
        $revision->setData($planting->snapshot());
        $this->assertSame([], $revision->getDiff());
    }
}
