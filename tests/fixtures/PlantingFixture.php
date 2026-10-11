<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class PlantingFixture extends ActiveFixture
{
    public $modelClass = 'app\models\Planting';
    public $dataFile = '@tests/fixtures/data/planting.php';
    public $depends = [UserFixture::class, TreeSpeciesFixture::class, AdCampaignFixture::class];
}
