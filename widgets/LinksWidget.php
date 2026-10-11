<?php

namespace app\widgets;

use app\models\PlantingLink;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * Список ссылок «Кто посадил» с иконками соцсетей и подписями.
 * Ссылки — rel="nofollow noopener ugc", target="_blank".
 */
class LinksWidget extends Widget
{
    /** @var PlantingLink[]|array[] */
    public array $links = [];
    public array $options = ['class' => 'planting-links list-unstyled mb-0'];
    public string $emptyText = '';

    public function run(): string
    {
        if (!$this->links) {
            return $this->emptyText !== '' ? Html::tag('p', Html::encode($this->emptyText), ['class' => 'text-muted']) : '';
        }
        $items = [];
        foreach ($this->links as $link) {
            if (is_array($link)) {
                $url = $link['url'];
                $label = $link['label'] ?: (parse_url($url, PHP_URL_HOST) ?: $url);
                $icon = $link['icon'] ?? 'bi bi-globe2';
                $network = $link['network'] ?? '';
            } else {
                $url = $link->url;
                $label = $link->getDisplayLabel();
                $icon = $link->getIconClass();
                $network = $link->getNetworkLabel();
            }
            if (!preg_match('~^https?://~i', $url)) {
                continue; // только http/https
            }
            $items[] = Html::tag('li',
                Html::a(
                    Html::tag('i', '', ['class' => $icon, 'aria-hidden' => 'true']) . ' ' . Html::encode($label),
                    $url,
                    ['rel' => 'nofollow noopener ugc', 'target' => '_blank', 'title' => $network, 'class' => 'planting-link']
                ),
                ['class' => 'mb-1']
            );
        }
        return Html::tag('ul', implode("\n", $items), $this->options);
    }
}
