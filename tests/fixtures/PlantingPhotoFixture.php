<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class PlantingPhotoFixture extends ActiveFixture
{
    public $modelClass = 'app\models\PlantingPhoto';
    public $dataFile = '@tests/fixtures/data/planting_photo.php';
    public $depends = [PlantingFixture::class];
}
