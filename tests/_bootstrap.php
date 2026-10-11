<?php

define('YII_ENV', 'test');
defined('YII_DEBUG') or define('YII_DEBUG', true);

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../vendor/autoload.php';

// Тестовое изображение для загрузки фото
$jpeg = __DIR__ . '/_data/tree.jpg';
if (!is_file($jpeg) && function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor(640, 480);
    imagefill($img, 0, 0, imagecolorallocate($img, 60, 140, 70));
    imagejpeg($img, $jpeg, 85);
    imagedestroy($img);
}
