<?php

namespace app\jobs;

use app\components\sms\SmsSenderInterface;
use Yii;
use yii\base\BaseObject;
use yii\queue\RetryableJobInterface;

/**
 * Отправка SMS в фоне.
 */
class SendSmsJob extends BaseObject implements RetryableJobInterface
{
    public string $phone = '';
    public string $text = '';

    public function execute($queue): void
    {
        /** @var SmsSenderInterface $sender */
        $sender = Yii::$app->get('smsSender');
        if (!$sender->send($this->phone, $this->text)) {
            throw new \RuntimeException("SMS to {$this->phone} not sent");
        }
    }

    public function getTtr(): int
    {
        return 60;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt < 3;
    }
}
