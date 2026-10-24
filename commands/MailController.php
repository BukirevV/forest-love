<?php

namespace app\commands;

use app\jobs\SendEmailJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Проверка отправки почты.
 *
 *   yii mail/config                 — текущие настройки почты (пароль скрыт)
 *   yii mail/test you@example.com   — отправить тестовое письмо сразу, без очереди, с полным ответом SMTP
 *   yii mail/queue you@example.com  — поставить тестовое письмо в очередь (проверка обработчика)
 */
class MailController extends Controller
{
    public function actionConfig(): int
    {
        $mailer = Yii::$app->mailer;
        $dsn = (string)($_ENV['MAILER_DSN'] ?? '');
        $this->stdout('Окружение (YII_ENV):    ' . YII_ENV . "\n");
        $this->stdout('Сохранять в файлы:      ' . ($mailer->useFileTransport ? 'ДА — письма НЕ отправляются, а пишутся в ' . Yii::getAlias($mailer->fileTransportPath) : 'нет') . "\n");
        $this->stdout('MAILER_USE_FILE_TRANSPORT = ' . var_export($_ENV['MAILER_USE_FILE_TRANSPORT'] ?? null, true) . "\n");
        $this->stdout('MAILER_DSN:             ' . ($dsn === '' ? '(не задан)' : self::maskDsn($dsn)) . "\n");
        $parts = parse_url($dsn) ?: [];
        if ($dsn !== '' && empty($parts['host'])) {
            $this->stderr("  ✘ DSN не разобран. Закодируйте спецсимволы в логине/пароле (@ → %40, : → %3A, / → %2F, # → %23).\n");
        } elseif (!empty($parts['user'])) {
            $this->stdout('  логин:  ' . urldecode($parts['user']) . "\n");
            $this->stdout('  сервер: ' . $parts['host'] . ':' . ($parts['port'] ?? '(порт по умолчанию)') . "\n");
        }
        $sender = (string)(Yii::$app->params['senderEmail'] ?? '');
        $this->stdout('Отправитель (SENDER_EMAIL): ' . ($sender ?: '(не задан)') . "\n");
        if (!empty($parts['user']) && $sender && strcasecmp(urldecode($parts['user']), $sender) !== 0) {
            $this->stdout("  ⚠ Отправитель не совпадает с логином SMTP — Яндекс и Mail.ru такие письма отклоняют.\n");
        }
        return ExitCode::OK;
    }

    public function actionTest(string $to): int
    {
        $this->actionConfig();
        $this->stdout("\nОтправка на {$to}…\n");
        $appName = Yii::$app->params['appName'] ?? 'Лес Любви';
        try {
            $ok = Yii::$app->mailer->compose()
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName'] ?? $appName])
                ->setTo($to)
                ->setSubject('Тестовое письмо — ' . $appName)
                ->setTextBody('Если вы читаете это письмо, отправка почты настроена. ' . date('Y-m-d H:i:s'))
                ->send();
        } catch (\Throwable $e) {
            $this->stderr("✘ Ошибка: " . get_class($e) . "\n" . $e->getMessage() . "\n");
            if (method_exists($e, 'getDebug') && $e->getDebug()) {
                $this->stderr("\nДиалог с SMTP-сервером:\n" . $e->getDebug() . "\n");
            }
            return ExitCode::UNAVAILABLE;
        }
        if (!$ok) {
            $this->stderr("✘ Почтовый компонент вернул false, подробности в runtime/logs/app.log\n");
            return ExitCode::UNAVAILABLE;
        }
        $this->stdout(Yii::$app->mailer->useFileTransport
            ? "✔ Письмо сохранено в файл (режим файлов включён), на почту оно не уйдёт.\n"
            : "✔ SMTP-сервер принял письмо. Если его нет во входящих — проверьте «Спам» и записи SPF/DKIM домена.\n");
        return ExitCode::OK;
    }

    public function actionQueue(string $to): int
    {
        $id = Yii::$app->queue->push(new SendEmailJob([
            'to' => $to,
            'subject' => 'Тест очереди',
            'template' => 'confirmEmail',
            'params' => ['url' => Yii::$app->params['appUrl'] ?? 'https://example.com', 'email' => $to],
        ]));
        $this->stdout("Задача #{$id} поставлена в очередь. Проверьте: php yii queue/info и runtime/logs/queue.log\n");
        return ExitCode::OK;
    }

    private static function maskDsn(string $dsn): string
    {
        return preg_replace('#(://[^:/@]*:)[^@]*@#', '$1***@', $dsn);
    }
}
