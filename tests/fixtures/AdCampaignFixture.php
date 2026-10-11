<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class AdCampaignFixture extends ActiveFixture
{
    public $modelClass = 'app\models\AdCampaign';
    public $dataFile = '@tests/fixtures/data/ad_campaign.php';
    public $depends = [AdvertiserFixture::class];
}
