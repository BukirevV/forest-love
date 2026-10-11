<?php

namespace app\commands;

use app\services\PaymentService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Платежи (cron).
 */
class PaymentController extends Controller
{
    public int $olderThan = 900;

    public function __construct($id, $module, private readonly PaymentService $payments, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['olderThan']);
    }

    /**
     * Сверка зависших платежей с провайдером (каждые 15 минут).
     */
    public function actionSync(): int
    {
        $stats = $this->payments->syncStale($this->olderThan);
        $this->stdout("Проверено: {$stats['checked']}, изменено: {$stats['changed']}, ошибок: {$stats['errors']}\n");
        return $stats['errors'] ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
