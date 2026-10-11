<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AdvertiserFixture extends ActiveFixture
{
    public $modelClass = 'app\models\Advertiser';
    public $dataFile = '@tests/fixtures/data/advertiser.php';

}
