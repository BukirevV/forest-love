<?php

use app\enums\CampaignStatus;
use app\enums\PlacementType;
use app\models\AdCampaign;
use app\models\AdPlacement;
use app\widgets\AdStatsWidget;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var AdCampaign $campaign */
/** @var array $stats */
/** @var AdPlacement[] $placements */

$this->title = $campaign->title;
$this->params['breadcrumbs'][] = ['label' => 'Кампании', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $campaign->status, 'enum' => CampaignStatus::class]) ?>
    <div class="ms-auto d-flex gap-2">
        <?= Html::a('Публичная страница', $campaign->getPublicUrl(), ['class' => 'btn btn-sm btn-outline-success', 'target' => '_blank']) ?>
        <?= Html::a('Редактировать', ['update', 'id' => $campaign->id], ['class' => 'btn btn-sm btn-primary']) ?>
        <?= Html::a('<i class="bi bi-map"></i> Привязать посадки на карте', ['plantings', 'id' => $campaign->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $campaign->id], ['class' => 'btn btn-sm btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Удалить кампанию? Посадки будут отвязаны.']) ?>
    </div>
</div>

<h2 class="h5">Статистика</h2>
<div class="mb-4"><?= AdStatsWidget::widget(['stats' => $stats]) ?></div>

<div class="row g-4">
    <div class="col-lg-6">
        <?= DetailView::widget(['model' => $campaign, 'attributes' => [
            ['attribute' => 'advertiser_id', 'format' => 'raw', 'value' => Html::a(Html::encode($campaign->advertiser->name), ['/admin/advertiser/view', 'id' => $campaign->advertiser_id])],
            'slug', 'starts_at:date', 'ends_at:date', 'funded_trees_target', 'legal_text:ntext', 'created_at:datetime',
        ]]) ?>
    </div>
    <div class="col-lg-6">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h5">Размещения</h2>
            <?= Html::a('<i class="bi bi-plus-lg"></i> Добавить', ['/admin/ad-placement/create', 'campaign_id' => $campaign->id], ['class' => 'btn btn-sm btn-success']) ?>
        </div>
        <table class="table table-sm">
            <thead><tr><th>Тип</th><th>Где</th><th>Показы</th><th>Клики</th><th>CTR</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($placements as $p): ?>
                <tr class="<?= $p->is_active ? '' : 'text-muted' ?>">
                    <td><?= Html::encode(PlacementType::labelOf($p->type)) ?></td>
                    <td><?= $p->isPoint() ? sprintf('%.4f, %.4f', $p->latitude, $p->longitude) : Html::encode(\app\enums\BannerPosition::labelOf($p->position)) ?></td>
                    <td><?= (int)$p->impressions ?></td>
                    <td><?= (int)$p->clicks ?></td>
                    <td><?= $p->getCtr() ?>%</td>
                    <td><?= Html::a('<i class="bi bi-pencil"></i>', ['/admin/ad-placement/update', 'id' => $p->id]) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
