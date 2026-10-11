<?php

namespace app\commands;

use app\enums\CampaignStatus;
use app\models\AdCampaign;
use app\services\MapDataService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Рекламные кампании (cron).
 */
class AdController extends Controller
{
    public function __construct($id, $module, private readonly MapDataService $mapData, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * Завершение кампаний с истёкшим ends_at.
     */
    public function actionFinish(): int
    {
        $count = AdCampaign::updateAll(
            ['status' => CampaignStatus::Finished->value, 'updated_at' => time()],
            ['and',
                ['status' => [CampaignStatus::Active->value, CampaignStatus::Paused->value]],
                ['not', ['ends_at' => null]],
                ['<', 'ends_at', time()],
            ]
        );
        if ($count) {
            $this->mapData->invalidateAds();
        }
        $this->stdout("Завершено кампаний: {$count}\n");
        return ExitCode::OK;
    }
}
