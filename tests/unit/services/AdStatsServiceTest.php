<?php

namespace tests\unit\services;

use app\models\AdCampaign;
use app\models\Planting;
use app\services\AdStatsService;
use Codeception\Test\Unit;
use tests\fixtures\PlantingFixture;
use Yii;

class AdStatsServiceTest extends Unit
{
    public function _fixtures(): array
    {
        return ['plantings' => PlantingFixture::class];
    }

    public function testAggregates(): void
    {
        $service = Yii::$container->get(AdStatsService::class);
        $campaign = AdCampaign::findOne(1);
        $service->invalidate(1);
        $stats = $service->getStats($campaign);

        $this->assertSame(2, $stats['trees_count']);
        $this->assertCount(2, $stats['species']);
        $this->assertSame('2026-05-01', $stats['first_date']);
        $this->assertSame('2026-05-04', $stats['last_date']);
        $this->assertSame(2, $stats['regions_count']); // Москва и Петербург — разные ячейки 1°×1°
        $this->assertSame(20.0, $stats['progress']);
    }

    public function testInvalidationOnHide(): void
    {
        $service = Yii::$container->get(AdStatsService::class);
        $campaign = AdCampaign::findOne(1);
        $this->assertSame(2, $service->getStats($campaign)['trees_count']);

        $planting = Planting::findOne(4);
        $planting->status = 'hidden';
        $planting->save(false, ['status']);
        $service->invalidateForPlanting($planting);

        $this->assertSame(1, $service->getStats($campaign)['trees_count']);
    }
}
