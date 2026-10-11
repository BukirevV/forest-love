<?php

namespace app\services;

use Yii;

/**
 * Определение соцсети по домену ссылки (таблица доменов в params['social.domains']).
 */
class SocialNetworkResolver
{
    public const SITE = 'site';

    private const ICONS = [
        'vk' => 'bi bi-chat-square-heart',
        'telegram' => 'bi bi-telegram',
        'instagram' => 'bi bi-instagram',
        'facebook' => 'bi bi-facebook',
        'youtube' => 'bi bi-youtube',
        'ok' => 'bi bi-people-fill',
        'x' => 'bi bi-twitter-x',
        'tiktok' => 'bi bi-tiktok',
        'dzen' => 'bi bi-journal-richtext',
        'rutube' => 'bi bi-play-btn',
        'linkedin' => 'bi bi-linkedin',
        'github' => 'bi bi-github',
        'pinterest' => 'bi bi-pinterest',
        'threads' => 'bi bi-threads',
        self::SITE => 'bi bi-globe2',
    ];

    private const LABELS = [
        'vk' => 'ВКонтакте',
        'telegram' => 'Telegram',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'ok' => 'Одноклассники',
        'x' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'dzen' => 'Дзен',
        'rutube' => 'Rutube',
        'linkedin' => 'LinkedIn',
        'github' => 'GitHub',
        'pinterest' => 'Pinterest',
        'threads' => 'Threads',
        self::SITE => 'Сайт',
    ];

    /** @var array<string, string> */
    private array $domains;

    public function __construct(?array $domains = null)
    {
        $this->domains = $domains ?? (Yii::$app->params['social.domains'] ?? []);
    }

    public function resolve(string $url): string
    {
        $host = parse_url(preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) ? $url : 'https://' . $url, PHP_URL_HOST);
        if (!$host) {
            return self::SITE;
        }
        $host = mb_strtolower($host);
        if (function_exists('idn_to_utf8') && str_contains($host, 'xn--')) {
            $host = idn_to_utf8($host) ?: $host;
        }
        $host = preg_replace('/^(www\.|m\.|mobile\.)/', '', $host);

        // Точное совпадение или поддомен (например, music.youtube.com → youtube)
        $parts = explode('.', $host);
        while (count($parts) >= 2) {
            $candidate = implode('.', $parts);
            if (isset($this->domains[$candidate])) {
                return $this->domains[$candidate];
            }
            array_shift($parts);
        }
        return self::SITE;
    }

    public static function iconClass(?string $network): string
    {
        return self::ICONS[$network ?? self::SITE] ?? self::ICONS[self::SITE];
    }

    public static function label(?string $network): string
    {
        return self::LABELS[$network ?? self::SITE] ?? self::LABELS[self::SITE];
    }
}
