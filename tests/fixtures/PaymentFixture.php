<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class PaymentFixture extends ActiveFixture
{
    public $modelClass = 'app\models\Payment';
    public $dataFile = '@tests/fixtures/data/payment.php';
    public $depends = [PlantingFixture::class];
}
