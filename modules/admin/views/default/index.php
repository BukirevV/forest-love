<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var array $queue */
/** @var array $stats */

$this->title = 'Обзор';
$f = Yii::$app->formatter;
$tile = static fn(string $label, $value, string $icon, $url = null, string $color = 'success') =>
    Html::tag('div', Html::tag($url ? 'a' : 'div',
        Html::tag('div', "<i class=\"bi {$icon} fs-3 text-{$color}\"></i>", ['class' => 'mb-1'])
        . Html::tag('div', Html::encode((string)$value), ['class' => 'fs-3 fw-bold'])
        . Html::tag('div', Html::encode($label), ['class' => 'small text-muted']),
        array_filter(['class' => 'card card-body h-100 text-decoration-none text-reset', 'href' => $url ? \yii\helpers\Url::to($url) : null])
    ), ['class' => 'col-6 col-lg-3']);
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<h2 class="h6 text-muted">Очередь модерации</h2>
<div class="row g-3 mb-4">
    <?= $tile('Новые карточки', $queue['plantings'], 'bi-tree', ['/admin/moderation/index', 'tab' => 'plantings'], $queue['plantings'] ? 'danger' : 'success') ?>
    <?= $tile('Изменения карточек', $queue['revisions'], 'bi-pencil-square', ['/admin/moderation/index', 'tab' => 'revisions'], $queue['revisions'] ? 'danger' : 'success') ?>
    <?= $tile('Фотографии', $queue['photos'], 'bi-images', ['/admin/moderation/index', 'tab' => 'photos'], $queue['photos'] ? 'danger' : 'success') ?>
</div>

<h2 class="h6 text-muted">Показатели</h2>
<div class="row g-3">
    <?= $tile('Деревьев на карте', $f->asInteger($stats['published']), 'bi-geo-alt') ?>
    <?= $tile('Именных деревьев', $f->asInteger($stats['named']), 'bi-star-fill', null, 'warning') ?>
    <?= $tile('Активных пользователей', $f->asInteger($stats['users']) . ' (+' . $stats['usersToday'] . ' сегодня)', 'bi-people') ?>
    <?= $tile('Платежей в ожидании', $stats['paymentsPending'], 'bi-hourglass-split', ['/admin/payment/index', 'PaymentSearch[status]' => 'pending'], 'warning') ?>
    <?= $tile('Оплачено сегодня', $f->asCurrency($stats['paymentsToday']), 'bi-cash-coin') ?>
    <?= $tile('Активных кампаний', $stats['campaignsRunning'], 'bi-megaphone', ['/admin/ad-campaign/index', 'AdCampaignSearch[status]' => 'active'], 'info') ?>
</div>
