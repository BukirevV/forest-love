<?php

use app\models\AdCampaign;
use app\widgets\MapWidget;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var AdCampaign $campaign */
/** @var array $points */
/** @var int[] $selected */

$this->title = 'Посадки кампании «' . $campaign->title . '»';
$this->params['breadcrumbs'][] = ['label' => 'Кампании', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $campaign->title, 'url' => ['view', 'id' => $campaign->id]];
$this->params['breadcrumbs'][] = 'Привязка посадок';

$selectedMap = array_flip($selected);
$mapPoints = array_map(static fn($p) => [
    'id' => (int)$p['id'],
    'lat' => (float)$p['latitude'],
    'lng' => (float)$p['longitude'],
    'selected' => isset($selectedMap[(int)$p['id']]),
    'status' => $p['status'],
], $points);
?>
<h1 class="h4 mb-2"><?= Html::encode($this->title) ?></h1>
<p class="small text-muted">Кликните по точке, чтобы добавить/убрать её из кампании. Shift + выделение прямоугольником (зажмите Shift и тяните мышью) — выбрать группу. Показаны посадки без кампании и посадки этой кампании.</p>

<?= Html::beginForm() ?>
    <?= Html::hiddenInput('planting_ids', implode(',', $selected), ['id' => 'selection-input']) ?>
    <?= MapWidget::widget([
        'mode' => MapWidget::MODE_SELECT,
        'points' => $mapPoints,
        'selectionInputId' => 'selection-input',
        'options' => ['class' => 'rounded mb-3', 'style' => 'height: 65vh'],
        'clientOptions' => ['counter' => '#selection-count'],
    ]) ?>
    <div class="d-flex align-items-center gap-3">
        <?= Html::submitButton('Сохранить привязку', ['class' => 'btn btn-success']) ?>
        <span>Выбрано: <strong id="selection-count"><?= count($selected) ?></strong></span>
    </div>
<?= Html::endForm() ?>
