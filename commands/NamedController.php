<?php

namespace app\commands;

use app\services\NamedTreeService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Именные размещения (cron).
 */
class NamedController extends Controller
{
    public function __construct($id, $module, private readonly NamedTreeService $namedTrees, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * Перевод истёкших именных размещений в expired (ежедневно).
     */
    public function actionExpire(): int
    {
        $count = $this->namedTrees->expire();
        $this->stdout("Истекло размещений: {$count}\n");
        return ExitCode::OK;
    }
}
