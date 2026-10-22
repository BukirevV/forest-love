<?php

namespace app\components\sms\smsaero;

/**
 * Ошибка обращения к API SMS Aero.
 * $httpCode — HTTP-код ответа (0 — сетевая ошибка), $retryable — имеет ли смысл повтор.
 */
class SmsAeroException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpCode = 0,
        public readonly array $response = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpCode, $previous);
    }

    /**
     * Повтор бесполезен при ошибке данных (400), нехватке денег (402),
     * неверной авторизации (401/403) и «не найдено» (404).
     */
    public function isRetryable(): bool
    {
        return $this->httpCode === 0 || $this->httpCode === 429 || $this->httpCode >= 500;
    }
}
