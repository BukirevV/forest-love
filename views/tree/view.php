<?php

use app\models\Planting;
use app\widgets\LinksWidget;
use app\widgets\MapWidget;
use app\widgets\PhotoTimelineWidget;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var Planting $tree */

$f = Yii::$app->formatter;
$title = $tree->getDisplayTitle();
$this->title = $title;
$this->params['breadcrumbs'][] = $title;

//Yii::debug($tree);
// Open Graph
$cover = $tree->mainPhoto?->isApproved() ? $tree->mainPhoto : ($tree->approvedPhotos ? end($tree->approvedPhotos) : null);
$description = trim(($tree->species?->name ?? '') . ($tree->planted_at ? ', посажено ' . $f->asDate($tree->planted_at, 'long') : ''));
$this->registerMetaTag(['property' => 'og:type', 'content' => 'article']);
$this->registerMetaTag(['property' => 'og:title', 'content' => $title . ' — ' . Yii::$app->name]);
$this->registerMetaTag(['property' => 'og:description', 'content' => $description]);
$this->registerMetaTag(['property' => 'og:url', 'content' => Url::to(['/tree/view', 'id' => $tree->id], true)]);
if ($cover) {
    $this->registerMetaTag(['property' => 'og:image', 'content' => Url::to($cover->getPublicUrl(), true)]);
}
$this->registerMetaTag(['name' => 'description', 'content' => $description]);
$this->registerLinkTag(['rel' => 'canonical', 'href' => Url::to(['/tree/view', 'id' => $tree->id], true)]);
?>
<article class="tree-view">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="mb-0"><?= Html::encode($title) ?></h1>
        <?php if ($tree->isNamed()): ?>
            <span class="badge text-bg-warning fs-6"><i class="bi bi-star-fill"></i> Именное дерево</span>
        <?php endif ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <?php if ($cover): ?>
                <img src="<?= Html::encode($cover->getPublicUrl()) ?>" alt="<?= Html::encode($title) ?>" class="img-fluid rounded mb-3 tree-view__cover">
            <?php endif ?>

            <?php if ($tree->description): ?>
                <p><?= nl2br(Html::encode($tree->description)) ?></p>
            <?php endif ?>

            <h2 class="h5 mt-4"><i class="bi bi-images"></i> История роста</h2>
            <?= PhotoTimelineWidget::widget(['photos' => $tree->approvedPhotos]) ?>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Порода</dt>
                        <dd class="col-7">
                            <?= Html::encode($tree->species?->name) ?>
                            <?php if ($tree->species?->latin_name): ?><div class="small text-muted fst-italic"><?= Html::encode($tree->species->latin_name) ?></div><?php endif ?>
                        </dd>
                        <dt class="col-5">Дата посадки</dt>
                        <dd class="col-7"><?= $tree->planted_at ? $f->asDate($tree->planted_at, 'long') : '—' ?></dd>
                        <dt class="col-5">Место</dt>
                        <dd class="col-7 small"><?= Html::encode(sprintf('%.5f, %.5f', $tree->latitude, $tree->longitude)) ?></dd>
                        <?php if ($tree->adCampaign): ?>
                            <dt class="col-5">Профинансировано</dt>
                            <dd class="col-7"><?= Html::a(Html::encode($tree->adCampaign->advertiser->name ?? $tree->adCampaign->title), $tree->adCampaign->getPublicUrl()) ?></dd>
                        <?php endif ?>
                    </dl>
                </div>
            </div>

            <?= MapWidget::widget([
                'mode' => MapWidget::MODE_STATIC,
                'marker' => [(float)$tree->latitude, (float)$tree->longitude],
                'markerNamed' => $tree->isNamed(),
                'zoom' => 14,
                'options' => ['class' => 'rounded mb-3', 'style' => 'height: 260px'],
            ]) ?>
            <p class="small"><?= Html::a('<i class="bi bi-map"></i> Показать на большой карте', ['/site/index', 'tree' => $tree->id]) ?></p>

            <h2 class="h5"><i class="bi bi-people"></i> Посадили</h2>
            <?= LinksWidget::widget(['links' => $tree->links, 'emptyText' => 'Участники не указаны.']) ?>

            <div class="mt-4">
                <span class="small text-muted">Поделиться:</span>
                <?php $shareUrl = rawurlencode(Url::to(['/tree/view', 'id' => $tree->id], true)); $shareText = rawurlencode($title); ?>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://vk.com/share.php?url=<?= $shareUrl ?>"><i class="bi bi-chat-square-heart"></i> VK</a>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareText ?>"><i class="bi bi-telegram"></i> Telegram</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= Html::encode(Url::to(['/tree/view', 'id' => $tree->id], true)) ?>"><i class="bi bi-link-45deg"></i> Ссылка</button>
            </div>
        </div>
    </div>
</article>
