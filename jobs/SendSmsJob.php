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
        if ($sender->send($this->phone, $this->text)) {
            return;
        }
        // Провайдер сообщил об ошибке, повтор которой бесполезен (нет денег, неверные данные) — не повторяем
        if (method_exists($sender, 'getLastError')) {
            $error = $sender->getLastError();
            if ($error !== null && method_exists($error, 'isRetryable') && !$error->isRetryable()) {
                Yii::error("SMS to {$this->phone} rejected by provider: {$error->getMessage()}", 'sms');
                return;
            }
        }
        throw new \RuntimeException("SMS to {$this->phone} not sent");
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
