<?php

namespace app\widgets;

use app\assets\MapAsset;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Инициализация Leaflet (ТЗ 13).
 *
 * Режимы:
 *  - public   — все опубликованные точки по bbox, рекламные точки, фильтры;
 *  - picker   — выбор одной точки в форме (связь с полями lat/lng, кнопка «Моё местоположение»);
 *  - static   — мини-карта карточки (одна точка, без взаимодействия);
 *  - campaign — точки одной кампании (переданы массивом points);
 *  - select   — выбор множества точек (админка: привязка посадок к кампании).
 */
class MapWidget extends Widget
{
    public const MODE_PUBLIC = 'public';
    public const MODE_PICKER = 'picker';
    public const MODE_STATIC = 'static';
    public const MODE_CAMPAIGN = 'campaign';
    public const MODE_SELECT = 'select';

    public string $mode = self::MODE_PUBLIC;
    /** @var float[]|null [lat, lng] */
    public ?array $center = null;
    public ?int $zoom = null;
    public array $options = [];

    /** Для picker: id полей широты/долготы/источника координат. */
    public ?string $latInputId = null;
    public ?string $lngInputId = null;
    public ?string $sourceInputId = null;

    /** Для static/picker: текущая точка [lat, lng]. */
    public ?array $marker = null;
    public bool $markerNamed = false;

    /** Для campaign/select: [['id'=>, 'lat'=>, 'lng'=>, 'selected'=>bool, 'named'=>bool]] */
    public array $points = [];
    /** Для select: id скрытого поля со списком выбранных id. */
    public ?string $selectionInputId = null;

    /** Дополнительные параметры JS-конфигурации. */
    public array $clientOptions = [];

    public function run(): string
    {
        MapAsset::register($this->view);
        $params = Yii::$app->params;

        $id = $this->options['id'] ??= $this->getId();
        Html::addCssClass($this->options, ['forest-map', 'forest-map--' . $this->mode]);

        $config = array_merge([
            'mode' => $this->mode,
            'center' => $this->center ?? $this->marker ?? $params['map.defaultCenter'],
            'zoom' => $this->zoom ?? ($this->marker ? 15 : $params['map.defaultZoom']),
            'tileUrl' => $params['map.tileUrl'],
            'tileAttribution' => $params['map.tileAttribution'],
            'marker' => $this->marker,
            'markerNamed' => $this->markerNamed,
            'latInput' => $this->latInputId,
            'lngInput' => $this->lngInputId,
            'sourceInput' => $this->sourceInputId,
            'points' => $this->points,
            'selectionInput' => $this->selectionInputId,
            'urls' => [
                'points' => Url::to(['/map/points']),
                'tree' => Url::to(['/map/tree', 'id' => '__ID__']),
                'treePage' => Url::to(['/tree/view', 'id' => '__ID__']),
                'ads' => Url::to(['/map/ad-points']),
                'adClick' => Url::to(['/map/ad-click', 'id' => '__ID__']),
            ],
            'icons' => [
                'tree' => Yii::getAlias('@web/img/marker-tree.svg'),
                'named' => Yii::getAlias('@web/img/marker-named.svg'),
                'ad' => Yii::getAlias('@web/img/marker-ad.svg'),
            ],
        ], $this->clientOptions);

        $this->view->registerJs('ForestMap.init(' . Json::htmlEncode($id) . ', ' . Json::htmlEncode($config) . ');');

        return Html::tag('div', '', $this->options);
    }
}
