<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Основные стили и скрипты, Bootstrap 5, иконки.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = ['css/site.css'];
    public $js = ['js/app.js'];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
        BootstrapIconsAsset::class,
    ];
}
