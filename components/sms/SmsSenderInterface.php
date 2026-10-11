<?php

namespace app\components\sms;

/**
 * Отправка SMS (ТЗ 9.1).
 */
interface SmsSenderInterface
{
    /**
     * @param string $phone номер в формате E.164
     */
    public function send(string $phone, string $text): bool;
}
