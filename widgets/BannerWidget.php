<?php

namespace app\widgets;

use app\services\MapDataService;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Баннеры поверх карты по позициям; учёт показов (пакетный POST) и кликов (POST перед переходом).
 */
class BannerWidget extends Widget
{
    /** @var array|null Баннеры; по умолчанию — активные из MapDataService */
    public ?array $banners = null;

    public function run(): string
    {
        $banners = $this->banners ?? Yii::$container->get(MapDataService::class)->banners();
        if (!$banners) {
            return '';
        }
        $html = [];
        $ids = [];
        foreach ($banners as $b) {
            $ids[] = $b['id'];
            $picture = Html::tag('picture',
                ($b['imageMobile'] ? Html::tag('source', '', ['media' => '(max-width: 767px)', 'srcset' => $b['imageMobile']]) : '')
                . Html::img($b['image'], ['alt' => $b['title'], 'class' => 'map-banner__img'])
            );
            $link = Html::a($picture, $b['url'], [
                'class' => 'map-banner__link',
                'data-ad-click' => Url::to(['/map/ad-click', 'id' => $b['id']]),
                'target' => $b['external'] ? '_blank' : null,
                'rel' => $b['external'] ? 'sponsored noopener' : null,
            ]);
            // Маркировка: текст кампании (обычно уже начинается со слова «Реклама») или просто «Реклама»
            $legal = trim(strip_tags((string)$b['legal']));
            $labelText = $legal === '' ? 'Реклама' : (mb_stripos($legal, 'реклама') === 0 ? $legal : 'Реклама · ' . $legal);
            $label = Html::tag('span', Html::encode(mb_strimwidth($labelText, 0, 90, '…')), ['class' => 'map-banner__label']);
            $close = Html::button('<i class="bi bi-x"></i>', ['class' => 'map-banner__close', 'aria-label' => 'Скрыть', 'data-banner-close' => true]);
            $html[] = Html::tag('div', $link . $label . $close, [
                'class' => 'map-banner map-banner--' . $b['position'],
                'data-banner-id' => $b['id'],
            ]);
        }
        $this->view->registerJs('ForestMap.registerImpressions(' . Json::htmlEncode([
            'url' => Url::to(['/map/ad-impression']),
            'ids' => $ids,
        ]) . ');');
        return implode("\n", $html);
    }
}
