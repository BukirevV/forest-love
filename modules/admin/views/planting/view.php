<?php

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\models\Planting;
use app\widgets\LinksWidget;
use app\widgets\MapWidget;
use app\widgets\PhotoTimelineWidget;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var Planting $planting */
/** @var array $campaigns */

$this->title = 'Посадка #' . $planting->id;
$this->params['breadcrumbs'][] = ['label' => 'Посадки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $planting->status, 'enum' => PlantingStatus::class]) ?>
    <?= StatusBadgeWidget::widget(['status' => $planting->named_status, 'enum' => NamedStatus::class]) ?>
    <div class="ms-auto d-flex gap-2">
        <?php if ($planting->isPublished()): ?>
            <?= Html::a('Публичная страница', $planting->getPublicUrl(), ['class' => 'btn btn-sm btn-outline-success', 'target' => '_blank']) ?>
        <?php endif ?>
        <?= Html::a('Модерация', ['/admin/moderation/planting', 'id' => $planting->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
        <?php if ($planting->isHidden()): ?>
            <?= Html::a('Восстановить', ['restore', 'id' => $planting->id], ['class' => 'btn btn-sm btn-outline-success', 'data-method' => 'post']) ?>
        <?php else: ?>
            <?= Html::a('Скрыть', ['hide', 'id' => $planting->id], ['class' => 'btn btn-sm btn-outline-dark', 'data-method' => 'post', 'data-confirm' => 'Скрыть карточку?']) ?>
        <?php endif ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <?= DetailView::widget([
            'model' => $planting,
            'attributes' => [
                'species.name:text:Порода',
                'title',
                'description:ntext',
                'planted_at:date',
                'latitude',
                'longitude',
                ['attribute' => 'coords_source', 'value' => \app\enums\CoordsSource::labelOf($planting->coords_source)],
                ['attribute' => 'user_id', 'format' => 'raw', 'value' => Html::a(Html::encode($planting->user->getPrivateName()), ['/admin/user/view', 'id' => $planting->user_id])],
                ['label' => 'Кто посадил', 'format' => 'raw', 'value' => LinksWidget::widget(['links' => $planting->links])],
                'approved_at:datetime',
                'moderation_comment:ntext',
                'named_until:date',
                'created_at:datetime',
                'updated_at:datetime',
            ],
        ]) ?>
        <h2 class="h6">Фотографии</h2>
        <?= PhotoTimelineWidget::widget(['photos' => $planting->photos]) ?>
    </div>
    <div class="col-lg-5">
        <?= MapWidget::widget([
            'mode' => MapWidget::MODE_STATIC,
            'marker' => [(float)$planting->latitude, (float)$planting->longitude],
            'options' => ['class' => 'rounded mb-3', 'style' => 'height: 240px'],
        ]) ?>

        <div class="card mb-3"><div class="card-body">
            <h2 class="h6">Рекламная кампания</h2>
            <?= Html::beginForm(['set-campaign', 'id' => $planting->id]) ?>
                <div class="input-group">
                    <?= Html::dropDownList('ad_campaign_id', $planting->ad_campaign_id, $campaigns, ['class' => 'form-select', 'prompt' => '— не привязана —']) ?>
                    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>
                </div>
            <?= Html::endForm() ?>
        </div></div>

        <div class="card"><div class="card-body">
            <h2 class="h6">Именной статус (вручную)</h2>
            <?= Html::beginForm(['set-named', 'id' => $planting->id]) ?>
                <?= Html::dropDownList('named_status', $planting->named_status, NamedStatus::labels(), ['class' => 'form-select mb-2']) ?>
                <?= Html::input('date', 'named_until', $planting->named_until ? date('Y-m-d', $planting->named_until) : '', ['class' => 'form-control mb-2']) ?>
                <div class="form-text mb-2">Срок действия (пусто — бессрочно).</div>
                <?= Html::submitButton('Применить', ['class' => 'btn btn-warning', 'data-confirm' => 'Изменить именной статус вручную?']) ?>
            <?= Html::endForm() ?>
        </div></div>
    </div>
</div>
