<?php

namespace app\components\sms\smsaero;

/**
 * Сообщение в ответе SMS Aero (sms/send, sms/status).
 */
final class SmsAeroMessage
{
    public const STATUS_QUEUE = 0;
    public const STATUS_DELIVERED = 1;
    public const STATUS_UNDELIVERED = 2;
    public const STATUS_SENT = 3;
    public const STATUS_WAIT = 4;
    public const STATUS_REJECTED = 6;
    public const STATUS_MODERATION = 8;

    private const LABELS = [
        self::STATUS_QUEUE => 'в очереди',
        self::STATUS_DELIVERED => 'доставлено',
        self::STATUS_UNDELIVERED => 'не доставлено',
        self::STATUS_SENT => 'передано оператору',
        self::STATUS_WAIT => 'ожидание статуса',
        self::STATUS_REJECTED => 'отклонено',
        self::STATUS_MODERATION => 'на модерации',
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $number,
        public readonly int $status,
        public readonly string $extendStatus,
        public readonly ?string $sign = null,
        public readonly ?string $channel = null,
        public readonly ?float $cost = null,
        public readonly array $raw = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            number: (string)($data['number'] ?? ''),
            status: (int)($data['status'] ?? -1),
            extendStatus: (string)($data['extendStatus'] ?? ''),
            sign: isset($data['from']) ? (string)$data['from'] : null,
            channel: isset($data['channel']) ? (string)$data['channel'] : null,
            cost: isset($data['cost']) ? (float)$data['cost'] : null,
            raw: $data,
        );
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? ($this->extendStatus ?: 'неизвестно');
    }

    /** Сообщение не будет доставлено (отклонено модерацией или оператором). */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_UNDELIVERED, self::STATUS_REJECTED], true);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
