<?php

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\forms\PhotoUploadForm;
use app\models\Planting;
use app\models\PlantingRevision;
use app\widgets\LinksWidget;
use app\widgets\MapWidget;
use app\widgets\PhotoTimelineWidget;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Planting $planting */
/** @var PhotoUploadForm $photoForm */
/** @var PlantingRevision|null $revision */
/** @var PlantingRevision|null $lastRejectedRevision */

$f = Yii::$app->formatter;
$this->title = $planting->getDisplayTitle();
$this->params['breadcrumbs'][] = ['label' => 'Мои посадки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= Html::encode($this->title) ?></h1>
        <?= StatusBadgeWidget::widget(['status' => $planting->status, 'enum' => PlantingStatus::class]) ?>
        <?= $planting->named_status !== NamedStatus::None->value
            ? StatusBadgeWidget::widget(['status' => $planting->named_status, 'enum' => NamedStatus::class]) : '' ?>
        <?php if ($planting->isNamed() && $planting->named_until): ?>
            <span class="small text-muted">до <?= $f->asDate($planting->named_until) ?></span>
        <?php endif ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($planting->isPublished()): ?>
            <?= Html::a('<i class="bi bi-eye"></i> Публичная страница', $planting->getPublicUrl(), ['class' => 'btn btn-outline-success', 'target' => '_blank']) ?>
        <?php endif ?>
        <?= Html::a('<i class="bi bi-pencil"></i> Редактировать', ['update', 'id' => $planting->id], ['class' => 'btn btn-outline-secondary']) ?>
        <?php if ($planting->canOrderNamed()): ?>
            <?= Html::a('<i class="bi bi-star"></i> Сделать именным', ['/payment/named', 'id' => $planting->id], ['class' => 'btn btn-warning']) ?>
        <?php endif ?>
        <?php if ($planting->canBeSubmitted()): ?>
            <?= Html::a('<i class="bi bi-send"></i> Отправить на модерацию', ['submit', 'id' => $planting->id], ['class' => 'btn btn-success', 'data-method' => 'post']) ?>
        <?php endif ?>
        <?= Html::a('<i class="bi bi-trash"></i>', ['delete', 'id' => $planting->id], [
            'class' => 'btn btn-outline-danger', 'title' => 'Удалить',
            'data-method' => 'post', 'data-confirm' => 'Удалить карточку? Дерево исчезнет с карты.',
        ]) ?>
    </div>
</div>

<?php if ($planting->status === PlantingStatus::Rejected->value && $planting->moderation_comment): ?>
    <div class="alert alert-danger"><strong>Комментарий модератора:</strong> <?= Html::encode($planting->moderation_comment) ?>
        <div class="small mt-1">Исправьте карточку и отправьте её повторно.</div></div>
<?php endif ?>
<?php if ($revision): ?>
    <div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Изменения от <?= $f->asDatetime($revision->created_at) ?> ожидают модерации. На карте отображается предыдущая версия.</div>
<?php elseif ($lastRejectedRevision && $lastRejectedRevision->updated_at > $planting->updated_at - 1): ?>
    <div class="alert alert-warning"><strong>Изменения отклонены:</strong> <?= Html::encode($lastRejectedRevision->moderation_comment) ?></div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <dl class="row">
            <dt class="col-sm-4">Порода</dt><dd class="col-sm-8"><?= Html::encode($planting->species?->name) ?></dd>
            <dt class="col-sm-4">Дата посадки</dt><dd class="col-sm-8"><?= $planting->planted_at ? $f->asDate($planting->planted_at, 'long') : '—' ?></dd>
            <dt class="col-sm-4">Координаты</dt>
            <dd class="col-sm-8"><?= Html::encode(sprintf('%.6f, %.6f', $planting->latitude, $planting->longitude)) ?>
                <span class="small text-muted">(<?= Html::encode(\app\enums\CoordsSource::labelOf($planting->coords_source)) ?>)</span></dd>
            <?php if ($planting->description): ?>
                <dt class="col-sm-4">Описание</dt><dd class="col-sm-8"><?= nl2br(Html::encode($planting->description)) ?></dd>
            <?php endif ?>
            <dt class="col-sm-4">Кто посадил</dt>
            <dd class="col-sm-8"><?= LinksWidget::widget(['links' => $planting->links, 'emptyText' => 'Не указано']) ?></dd>
            <?php if ($planting->adCampaign): ?>
                <dt class="col-sm-4">Профинансировано</dt><dd class="col-sm-8"><?= Html::encode($planting->adCampaign->title) ?></dd>
            <?php endif ?>
        </dl>
    </div>
    <div class="col-lg-5">
        <?= MapWidget::widget([
            'mode' => MapWidget::MODE_STATIC,
            'marker' => [(float)$planting->latitude, (float)$planting->longitude],
            'markerNamed' => $planting->isNamed(),
            'options' => ['class' => 'rounded', 'style' => 'height: 240px'],
        ]) ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h2 class="h5 mb-0"><i class="bi bi-images"></i> Фотографии</h2>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#photo-upload-modal" id="add-photo">
        <i class="bi bi-plus-lg"></i> Добавить фото
    </button>
</div>
<?= PhotoTimelineWidget::widget([
    'photos' => $planting->photos,
    'ownerMode' => true,
    'mainPhotoId' => $planting->main_photo_id,
]) ?>

<?= $this->render('_photo_upload', ['planting' => $planting, 'model' => $photoForm]) ?>
