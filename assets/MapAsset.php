<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Карта: Leaflet, markercluster, web/js/map.js.
 */
class MapAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = ['css/map.css'];
    public $js = ['js/map.js'];
    public $depends = [
        AppAsset::class,
        LeafletAsset::class,
        MarkerClusterAsset::class,
    ];
}
