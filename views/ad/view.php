<?php

use app\helpers\Html as AppHtml;
use app\models\AdCampaign;
use app\widgets\AdStatsWidget;
use app\widgets\MapWidget;
use yii\bootstrap5\Html;
use yii\data\ActiveDataProvider;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var AdCampaign $campaign */
/** @var array $stats */
/** @var ActiveDataProvider $plantings */

$advertiser = $campaign->advertiser;
$this->title = $campaign->title;
$this->params['breadcrumbs'][] = $advertiser->name;

$points = array_map(static fn($p) => [
    'id' => (int)$p['id'], 'lat' => (float)$p['latitude'], 'lng' => (float)$p['longitude'],
    'named' => $p['named_status'] === 'active',
], $campaign->getPublishedPlantings()->select(['id', 'latitude', 'longitude', 'named_status'])->asArray()->limit(5000)->all());
?>
<article class="ad-view">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <?php if ($advertiser->getLogoUrl()): ?>
            <img src="<?= Html::encode($advertiser->getLogoUrl()) ?>" alt="<?= Html::encode($advertiser->name) ?>" class="ad-view__logo">
        <?php endif ?>
        <div>
            <div class="small text-muted">Партнёр проекта · Реклама</div>
            <h1 class="mb-0"><?= Html::encode($advertiser->name) ?></h1>
            <div class="lead"><?= Html::encode($campaign->title) ?></div>
        </div>
        <?php if ($advertiser->website_url): ?>
            <?= Html::a('Перейти на сайт <i class="bi bi-box-arrow-up-right"></i>', $advertiser->website_url, [
                'class' => 'btn btn-success ms-md-auto', 'target' => '_blank', 'rel' => 'sponsored noopener',
            ]) ?>
        <?php endif ?>
    </header>

    <section class="mb-4"><?= AdStatsWidget::widget(['stats' => $stats]) ?></section>

    <section class="mb-4 marketing-text">
        <?= AppHtml::purifyMarketing($campaign->marketing_text) ?>
    </section>

    <?php if ($points): ?>
        <section class="mb-4">
            <h2 class="h4">Деревья на карте</h2>
            <?= MapWidget::widget([
                'mode' => MapWidget::MODE_CAMPAIGN,
                'points' => $points,
                'options' => ['class' => 'rounded', 'style' => 'height: 420px'],
            ]) ?>
        </section>
    <?php endif ?>

    <section class="mb-4">
        <h2 class="h4">Посадки</h2>
        <?= ListView::widget([
            'dataProvider' => $plantings,
            'itemView' => '_planting',
            'options' => ['class' => 'row g-3'],
            'itemOptions' => ['class' => 'col-6 col-md-4 col-lg-3'],
            'layout' => "{items}\n<div class=\"col-12\">{pager}</div>",
            'emptyText' => 'Посадки ещё не опубликованы.',
            'pager' => ['class' => \yii\bootstrap5\LinkPager::class],
        ]) ?>
    </section>

    <footer class="border-top pt-3 small text-muted">
        <h2 class="h6">Юридическая информация</h2>
        <p class="mb-1"><?= Html::encode($advertiser->legal_name) ?></p>
        <p class="mb-1"><?= nl2br(Html::encode($advertiser->legal_info)) ?></p>
        <?php if ($campaign->legal_text): ?>
            <p class="mb-0"><?= nl2br(Html::encode($campaign->legal_text)) ?></p>
        <?php endif ?>
    </footer>
</article>
