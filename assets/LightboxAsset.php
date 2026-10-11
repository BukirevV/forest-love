<?php

namespace app\assets;

use yii\web\AssetBundle;

class LightboxAsset extends AssetBundle
{
    public $sourcePath = '@npm/glightbox/dist';
    public $css = ['css/glightbox.min.css'];
    public $js = ['js/glightbox.min.js'];
}
