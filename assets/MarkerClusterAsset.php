<?php

namespace app\assets;

use yii\web\AssetBundle;

class MarkerClusterAsset extends AssetBundle
{
    public $sourcePath = '@npm/leaflet.markercluster/dist';
    public $css = ['MarkerCluster.css', 'MarkerCluster.Default.css'];
    public $js = ['leaflet.markercluster.js'];
    public $depends = [LeafletAsset::class];
}
