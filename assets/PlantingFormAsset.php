<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Форма посадки: камера, предпросмотр, EXIF, синхронизация маркера и полей, список ссылок.
 */
class PlantingFormAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = ['css/planting-form.css'];
    public $js = ['js/planting-form.js'];
    public $depends = [
        MapAsset::class,
        ExifrAsset::class,
    ];
}
