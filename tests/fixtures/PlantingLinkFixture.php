<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class PlantingLinkFixture extends ActiveFixture
{
    public $modelClass = 'app\models\PlantingLink';
    public $dataFile = '@tests/fixtures/data/planting_link.php';
    public $depends = [PlantingFixture::class];
}
