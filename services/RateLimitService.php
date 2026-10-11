<?php

namespace app\services;

use Yii;
use yii\caching\CacheInterface;

/**
 * Простое ограничение частоты на основе кэша (фиксированное окно).
 * Для продакшена рекомендуется Redis-кэш (атомарные инкременты).
 */
class RateLimitService
{
    private function cache(): CacheInterface
    {
        return Yii::$app->cache;
    }

    /**
     * Регистрирует попытку. Возвращает false, если лимит превышен.
     */
    public function hit(string $key, int $limit, int $window): bool
    {
        $cacheKey = ['rate-limit', $key];
        $data = $this->cache()->get($cacheKey);
        $now = time();
        if (!is_array($data) || $data['reset'] <= $now) {
            $data = ['count' => 0, 'reset' => $now + $window];
        }
        if ($data['count'] >= $limit) {
            return false;
        }
        $data['count']++;
        $this->cache()->set($cacheKey, $data, $data['reset'] - $now);
        return true;
    }

    public function isLimited(string $key, int $limit): bool
    {
        $data = $this->cache()->get(['rate-limit', $key]);
        return is_array($data) && $data['reset'] > time() && $data['count'] >= $limit;
    }

    /**
     * Интервал между действиями (например, повторная отправка кода не чаще 1 раза в 60 с).
     * Возвращает 0, если действие разрешено (и фиксирует его), иначе — сколько секунд ждать.
     */
    public function throttle(string $key, int $interval): int
    {
        $cacheKey = ['throttle', $key];
        $last = (int)$this->cache()->get($cacheKey);
        $wait = $last + $interval - time();
        if ($wait > 0) {
            return $wait;
        }
        $this->cache()->set($cacheKey, time(), $interval);
        return 0;
    }

    public function secondsUntilAllowed(string $key, int $interval): int
    {
        $last = (int)$this->cache()->get(['throttle', $key]);
        return max(0, $last + $interval - time());
    }

    public function reset(string $key): void
    {
        $this->cache()->delete(['rate-limit', $key]);
        $this->cache()->delete(['throttle', $key]);
    }
}
