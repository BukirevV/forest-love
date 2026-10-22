<?php

namespace app\commands;

use app\components\sms\smsaero\SmsAeroException;
use app\components\sms\smsaero\SmsAeroSender;
use app\components\sms\SmsSenderInterface;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Проверка SMS-шлюза.
 *
 *   yii sms/check                       — авторизация, баланс и имена отправителя (SMS Aero)
 *   yii sms/send +79991234567 "Текст"   — тестовая отправка через текущий драйвер
 *   yii sms/status 12345                — статус сообщения в SMS Aero
 */
class SmsController extends Controller
{
    public function actionCheck(): int
    {
        $sender = $this->sender();
        $this->stdout('Драйвер: ' . get_class($sender) . "\n");
        if (!$sender instanceof SmsAeroSender) {
            $this->stdout("Проверка доступна только для SMS Aero (SMS_DRIVER=smsaero).\n");
            return ExitCode::OK;
        }
        try {
            $sender->checkAuth();
            $this->stdout("Авторизация: OK ({$sender->email})\n");
            $this->stdout('Баланс: ' . number_format($sender->getBalance(), 2, ',', ' ') . " ₽\n");
            $signs = $sender->getSigns();
            $this->stdout('Имена отправителя: ' . ($signs ? implode(', ', $signs) : '—') . "\n");
            if ($signs && !in_array($sender->sign, $signs, true)) {
                $this->stderr("Внимание: SMSAERO_SIGN «{$sender->sign}» нет среди подтверждённых имён.\n");
                return ExitCode::CONFIG;
            }
            $this->stdout('Статусы доставки: ' . ($sender->callbackUrl ?: 'не запрашиваются') . "\n");
            return ExitCode::OK;
        } catch (SmsAeroException $e) {
            $this->stderr("Ошибка (HTTP {$e->httpCode}): {$e->getMessage()}\n");
            return ExitCode::UNAVAILABLE;
        }
    }

    public function actionSend(string $phone, string $text = 'Тестовое сообщение Лес Любви'): int
    {
        $sender = $this->sender();
        $ok = $sender->send($phone, $text);
        if ($sender instanceof SmsAeroSender) {
            if ($m = $sender->getLastMessage()) {
                $this->stdout("ID: {$m->id}, статус: {$m->statusLabel()}, канал: {$m->channel}, стоимость: {$m->cost}\n");
            }
            if ($e = $sender->getLastError()) {
                $this->stderr("Ошибка (HTTP {$e->httpCode}): {$e->getMessage()}\n");
            }
        }
        $this->stdout($ok ? "Отправлено.\n" : "Не отправлено.\n");
        return $ok ? ExitCode::OK : ExitCode::UNAVAILABLE;
    }

    public function actionStatus(int $id): int
    {
        $sender = $this->sender();
        if (!$sender instanceof SmsAeroSender) {
            $this->stderr("Только для SMS Aero.\n");
            return ExitCode::CONFIG;
        }
        try {
            $m = $sender->getStatus($id);
            $this->stdout("#{$m->id} → {$m->number}: {$m->statusLabel()} ({$m->extendStatus})\n");
            return ExitCode::OK;
        } catch (SmsAeroException $e) {
            $this->stderr("Ошибка (HTTP {$e->httpCode}): {$e->getMessage()}\n");
            return ExitCode::UNAVAILABLE;
        }
    }

    private function sender(): SmsSenderInterface
    {
        return Yii::$app->get('smsSender');
    }
}
