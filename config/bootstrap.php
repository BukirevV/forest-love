<?php

/**
 * Загрузка переменных окружения из .env (секреты не хранятся в репозитории).
 * Подключается из web/index.php и yii ДО Yii.php, а также из config/*.php (require_once).
 */

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    if (class_exists(\Dotenv\Dotenv::class)) {
        $dotenvVars = \Dotenv\Dotenv::parse((string)file_get_contents($root . '/.env'));
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    } else {
        // Резервный разбор KEY=VALUE, если vlucas/phpdotenv не установлен
        $dotenvVars = [];
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $dotenvVars[$key] = trim($value, "\"'");
        }
    }

    // Переменная может уже прийти из окружения процесса: так queue/listen передаёт их
    // дочернему queue/exec, так же их может задать systemd или php-fpm. Dotenv в режиме
    // immutable такие переменные не трогает и в $_ENV не кладёт, а при variables_order без "E"
    // $_ENV изначально пуст. Поэтому дописываем в $_ENV всё, что объявлено в .env:
    // значение окружения (если есть) имеет приоритет над файлом.
    foreach ($dotenvVars as $key => $value) {
        if (!array_key_exists($key, $_ENV)) {
            $fromEnv = $_SERVER[$key] ?? getenv($key);
            $_ENV[$key] = ($fromEnv !== false && $fromEnv !== null) ? $fromEnv : $value;
        }
    }
    unset($dotenvVars, $key, $value, $fromEnv, $line);
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

// Псевдонимы @storage и @uploads задаются в config/*.php ('aliases'),
// т.к. этот файл подключается до Yii.php (чтобы YII_ENV/YII_DEBUG брались из .env).
