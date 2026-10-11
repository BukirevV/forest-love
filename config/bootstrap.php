<?php

/**
 * Загрузка переменных окружения из .env (секреты не хранятся в репозитории).
 * Подключается из config/web.php, config/console.php и config/test.php.
 */

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    } else {
        // Резервный разбор KEY=VALUE, если vlucas/phpdotenv не установлен
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    /**
     * Чтение переменной окружения с приведением типов.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string)$value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

Yii::setAlias('@storage', $root . '/storage');
Yii::setAlias('@uploads', $root . '/web/uploads');
