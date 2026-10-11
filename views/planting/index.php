<?php

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\models\Planting;
use app\models\search\PlantingSearch;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\data\ActiveDataProvider;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var PlantingSearch $searchModel */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Мои посадки';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('<i class="bi bi-plus-circle"></i> Добавить дерево', ['create'], ['class' => 'btn btn-success']) ?>
</div>

<div class="mb-3 d-flex flex-wrap gap-1">
    <?= Html::a('Все', ['index'], ['class' => 'btn btn-sm ' . (!$searchModel->status ? 'btn-dark' : 'btn-outline-dark')]) ?>
    <?php foreach ([PlantingStatus::Approved, PlantingStatus::Pending, PlantingStatus::Draft, PlantingStatus::Rejected] as $s): ?>
        <?= Html::a($s->label(), ['index', 'PlantingSearch[status]' => $s->value], [
            'class' => 'btn btn-sm ' . ($searchModel->status === $s->value ? 'btn-dark' : 'btn-outline-dark'),
        ]) ?>
    <?php endforeach ?>
</div>

<?= ListView::widget([
    'dataProvider' => $dataProvider,
    'layout' => "{items}\n{pager}",
    'options' => ['class' => 'list-group mb-3'],
    'itemOptions' => ['tag' => false],
    'emptyText' => '<div class="text-center text-muted py-5"><i class="bi bi-tree display-4"></i><p>У вас пока нет посадок.</p>'
        . Html::a('Посадить первое дерево', ['create'], ['class' => 'btn btn-success']) . '</div>',
    'emptyTextOptions' => ['tag' => 'div'],
    'pager' => ['class' => LinkPager::class],
    'itemView' => static function (Planting $model) {
        $photo = $model->mainPhoto;
        $thumb = $photo ? Html::img($photo->getThumbUrl(), ['class' => 'rounded planting-thumb', 'alt' => '', 'loading' => 'lazy'])
            : Html::tag('div', '<i class="bi bi-tree"></i>', ['class' => 'planting-thumb planting-thumb--empty rounded']);
        $badges = StatusBadgeWidget::widget(['status' => $model->status, 'enum' => PlantingStatus::class]);
        if ($model->named_status !== NamedStatus::None->value) {
            $badges .= ' ' . StatusBadgeWidget::widget(['status' => $model->named_status, 'enum' => NamedStatus::class]);
        }
        if ($model->pendingRevision) {
            $badges .= ' <span class="badge text-bg-info">Изменения на модерации</span>';
        }
        $buttons = Html::a('<i class="bi bi-pencil"></i>', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Редактировать'])
            . Html::a('<i class="bi bi-camera"></i>', ['view', 'id' => $model->id, '#' => 'add-photo'], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Добавить фото']);
        if ($model->canOrderNamed()) {
            $buttons .= Html::a('<i class="bi bi-star"></i>', ['/payment/named', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-warning', 'title' => 'Сделать именным']);
        }
        return Html::tag('div',
            Html::a($thumb, ['view', 'id' => $model->id])
            . Html::tag('div',
                Html::a(Html::encode($model->getDisplayTitle()), ['view', 'id' => $model->id], ['class' => 'fw-semibold text-reset'])
                . Html::tag('div', ($model->planted_at ? Yii::$app->formatter->asDate($model->planted_at) : 'Дата не указана'), ['class' => 'small text-muted'])
                . Html::tag('div', $badges, ['class' => 'mt-1']),
                ['class' => 'flex-grow-1']
            )
            . Html::tag('div', $buttons, ['class' => 'btn-group align-self-center']),
            ['class' => 'list-group-item d-flex gap-3 align-items-start']
        );
    },
]) ?>
