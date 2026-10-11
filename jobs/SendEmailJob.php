<?php

namespace app\jobs;

use Yii;
use yii\base\BaseObject;
use yii\queue\RetryableJobInterface;

/**
 * Отправка письма по шаблону из mail/ в фоне.
 * В params модели передаются как ['__model' => Class, 'id' => N] и восстанавливаются перед рендерингом.
 */
class SendEmailJob extends BaseObject implements RetryableJobInterface
{
    public string $to = '';
    public string $subject = '';
    public string $template = '';
    public array $params = [];

    public function execute($queue): void
    {
        $params = $this->hydrate($this->params);
        $appName = Yii::$app->params['appName'] ?? 'Лес Любви';

        $sent = Yii::$app->mailer
            ->compose(['html' => $this->template . '-html', 'text' => $this->template . '-text'], $params)
            ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName'] ?? $appName])
            ->setTo($this->to)
            ->setSubject($this->subject . ' — ' . $appName)
            ->send();

        if (!$sent) {
            throw new \RuntimeException("Email '{$this->template}' to {$this->to} not sent");
        }
    }

    private function hydrate(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_array($value) && isset($value['__model'], $value['id'])) {
                $class = $value['__model'];
                $params[$key] = $class::findOne($value['id']);
            }
        }
        return $params;
    }

    public function getTtr(): int
    {
        return 60;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt < 5;
    }
}
