<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Leaflet (подложка OpenStreetMap; провайдер может быть заменён — params map.tileUrl).
 */
class LeafletAsset extends AssetBundle
{
    public $sourcePath = '@npm/leaflet/dist';
    public $css = ['leaflet.css'];
    public $js = ['leaflet.js'];
}
