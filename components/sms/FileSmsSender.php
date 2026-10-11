<?php

namespace app\components\sms;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

/**
 * Dev-реализация: SMS пишутся в лог и в файл @runtime/sms/{phone}.txt (последнее сообщение).
 * Используется в разработке и тестах.
 */
class FileSmsSender extends Component implements SmsSenderInterface
{
    public string $path = '@runtime/sms';

    /** @var array<int, array{phone: string, text: string}> отправленные в текущем процессе (для тестов) */
    public array $sent = [];

    public function send(string $phone, string $text): bool
    {
        $dir = Yii::getAlias($this->path);
        FileHelper::createDirectory($dir);
        $file = $dir . '/' . preg_replace('/\D/', '', $phone) . '.txt';
        file_put_contents($file, $text);
        file_put_contents($dir . '/all.log', sprintf("[%s] %s: %s\n", date('c'), $phone, $text), FILE_APPEND);
        Yii::info("SMS to {$phone}: {$text}", 'sms');
        $this->sent[] = ['phone' => $phone, 'text' => $text];
        return true;
    }

    public function getLastMessage(string $phone): ?string
    {
        $file = Yii::getAlias($this->path) . '/' . preg_replace('/\D/', '', $phone) . '.txt';
        return is_file($file) ? file_get_contents($file) : null;
    }
}
