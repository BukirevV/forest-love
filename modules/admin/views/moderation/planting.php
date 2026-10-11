<?php

use app\enums\ModerationAction;
use app\enums\PhotoStatus;
use app\enums\PlantingStatus;
use app\forms\ModerationForm;
use app\models\ModerationLog;
use app\models\Planting;
use app\widgets\LinksWidget;
use app\widgets\MapWidget;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Planting $planting */
/** @var ModerationForm $model */
/** @var ModerationLog[] $log */

$f = Yii::$app->formatter;
$this->title = 'Карточка #' . $planting->id;
$this->params['breadcrumbs'][] = ['label' => 'Модерация', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $planting->status, 'enum' => PlantingStatus::class]) ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h6">Фотографии</h2>
        <div class="row g-2 mb-3">
            <?php foreach ($planting->photos as $photo): ?>
                <div class="col-6 col-md-4">
                    <a href="<?= $photo->isProcessed() ? Html::encode($photo->getPublicUrl()) : $photo->getOriginalUrl() ?>" class="glightbox" data-gallery="mod">
                        <img src="<?= Html::encode($photo->isProcessed() ? $photo->getThumbUrl() : $photo->getOriginalUrl()) ?>" class="img-fluid rounded" alt="">
                    </a>
                    <div class="small mt-1">
                        <?= $f->asDate($photo->taken_at) ?>
                        <?= StatusBadgeWidget::widget(['status' => $photo->status, 'enum' => PhotoStatus::class]) ?>
                        <?= Html::a('оригинал', $photo->getOriginalUrl(), ['target' => '_blank', 'class' => 'ms-1']) ?>
                    </div>
                    <?php if ($photo->exif_latitude): ?>
                        <div class="small text-muted">EXIF: <?= sprintf('%.5f, %.5f', $photo->exif_latitude, $photo->exif_longitude) ?></div>
                    <?php endif ?>
                    <?php if ($photo->isPending()): ?>
                        <?= Html::beginForm(['reject'], 'post', ['class' => 'd-flex gap-1 mt-1']) ?>
                            <?= Html::hiddenInput('type', 'photo') . Html::hiddenInput('id', $photo->id) ?>
                            <?= Html::textInput('comment', '', ['class' => 'form-control form-control-sm', 'placeholder' => 'Причина']) ?>
                            <?= Html::submitButton('<i class="bi bi-x"></i>', ['class' => 'btn btn-sm btn-outline-danger', 'title' => 'Отклонить фото']) ?>
                        <?= Html::endForm() ?>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        <p class="small text-muted">При одобрении карточки ожидающие фото одобряются вместе с ней. Неподходящие фото отклоните заранее.</p>

        <dl class="row">
            <dt class="col-sm-4">Порода</dt><dd class="col-sm-8"><?= Html::encode($planting->species?->name) ?></dd>
            <dt class="col-sm-4">Дата посадки</dt><dd class="col-sm-8"><?= $planting->planted_at ? $f->asDate($planting->planted_at) : '—' ?></dd>
            <dt class="col-sm-4">Имя / подпись</dt><dd class="col-sm-8"><?= Html::encode($planting->title) ?: '—' ?></dd>
            <dt class="col-sm-4">Описание</dt><dd class="col-sm-8"><?= nl2br(Html::encode($planting->description)) ?: '—' ?></dd>
            <dt class="col-sm-4">Кто посадил</dt><dd class="col-sm-8"><?= LinksWidget::widget(['links' => $planting->links, 'emptyText' => '—']) ?></dd>
            <dt class="col-sm-4">Владелец</dt>
            <dd class="col-sm-8"><?= Yii::$app->user->can('manageUsers')
                ? Html::a(Html::encode($planting->user->getPrivateName()), ['/admin/user/view', 'id' => $planting->user_id])
                : Html::encode($planting->user->getPublicName()) ?></dd>
        </dl>
    </div>
    <div class="col-lg-5">
        <?= MapWidget::widget([
            'mode' => MapWidget::MODE_STATIC,
            'marker' => [(float)$planting->latitude, (float)$planting->longitude],
            'zoom' => 13,
            'options' => ['class' => 'rounded mb-2', 'style' => 'height: 280px'],
        ]) ?>
        <div class="small text-muted mb-3">
            <?= sprintf('%.6f, %.6f', $planting->latitude, $planting->longitude) ?> · <?= Html::encode(\app\enums\CoordsSource::labelOf($planting->coords_source)) ?>
        </div>

        <?php if ($planting->status === PlantingStatus::Pending->value): ?>
            <?= $this->render('_decision', ['model' => $model, 'allowHide' => true]) ?>
        <?php else: ?>
            <div class="alert alert-secondary">Карточка не в очереди модерации.</div>
        <?php endif ?>

        <?php if ($log): ?>
            <h2 class="h6 mt-4">История решений</h2>
            <ul class="list-unstyled small">
                <?php foreach ($log as $entry): ?>
                    <li class="border-bottom py-1">
                        <?= $f->asDatetime($entry->created_at) ?> — <?= Html::encode(ModerationAction::labelOf($entry->action)) ?>
                        (<?= Html::encode($entry->moderator->getPrivateName()) ?>)
                        <?php if ($entry->comment): ?><div><?= Html::encode($entry->comment) ?></div><?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>
