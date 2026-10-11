<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * exifr — чтение EXIF на клиенте.
 */
class ExifrAsset extends AssetBundle
{
    public $sourcePath = '@npm/exifr/dist';
    public $js = ['lite.umd.js'];
}
