<?php

use app\assets\PlantingFormAsset;
use app\enums\CoordsSource;
use app\forms\PlantingForm;
use app\widgets\LinksInputWidget;
use app\widgets\MapWidget;
use app\widgets\PhotoCaptureWidget;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PlantingForm $model */
/** @var array $speciesList */
/** @var bool $isCreate */
/** @var bool $isPublished */

PlantingFormAsset::register($this);
$isPublished ??= false;
$hasCoords = $model->latitude !== null && $model->latitude !== '';
?>
<?php $form = ActiveForm::begin([
    'id' => 'planting-form',
    'options' => ['enctype' => 'multipart/form-data', 'class' => 'planting-form', 'data-planting-form' => true],
]) ?>

<?php if ($isCreate): ?>
    <!-- Шаг 1. Фото -->
    <section class="card mb-3 planting-step">
        <div class="card-body">
            <h2 class="h5"><span class="step-num">1</span> Фото посадки</h2>
            <p class="small text-muted">Сфотографируйте дерево на месте посадки — координаты и дата подставятся из фото автоматически.</p>
            <?= $form->field($model, 'photo', ['template' => "{input}\n{error}"])->widget(PhotoCaptureWidget::class, [
                'takenAtInputId' => Html::getInputId($model, 'photo_taken_at'),
                'sourceInputId' => Html::getInputId($model, 'photo_source'),
            ]) ?>
            <?= Html::activeHiddenInput($model, 'photo_source') ?>
            <div class="row mt-2">
                <div class="col-sm-6">
                    <?= $form->field($model, 'photo_taken_at')->input('date', ['max' => date('Y-m-d')]) ?>
                </div>
            </div>
        </div>
    </section>
<?php endif ?>

<!-- Шаг 2. Место -->
<section class="card mb-3 planting-step">
    <div class="card-body">
        <h2 class="h5"><span class="step-num"><?= $isCreate ? 2 : 1 ?></span> Место посадки</h2>

        <div class="alert alert-info d-none align-items-center gap-2 flex-wrap" data-exif-offer role="alert">
            <i class="bi bi-geo-alt-fill"></i>
            <span>В фото найдены координаты места съёмки. Использовать их?</span>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-exif-accept>Использовать</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-exif-decline>Нет, укажу сам</button>
        </div>

        <p class="small text-muted mb-2">Нажмите на карту или перетащите маркер, чтобы указать точное место.</p>
        <?= MapWidget::widget([
            'mode' => MapWidget::MODE_PICKER,
            'marker' => $hasCoords ? [(float)$model->latitude, (float)$model->longitude] : null,
            'latInputId' => Html::getInputId($model, 'latitude'),
            'lngInputId' => Html::getInputId($model, 'longitude'),
            'sourceInputId' => Html::getInputId($model, 'coords_source'),
            'options' => ['id' => 'picker-map', 'class' => 'rounded picker-map'],
        ]) ?>
        <div class="row g-2 mt-2">
            <div class="col-6"><?= $form->field($model, 'latitude')->textInput(['inputmode' => 'decimal', 'data-coord' => 'lat']) ?></div>
            <div class="col-6"><?= $form->field($model, 'longitude')->textInput(['inputmode' => 'decimal', 'data-coord' => 'lng']) ?></div>
        </div>
        <?= Html::activeHiddenInput($model, 'coords_source', ['value' => $model->coords_source ?: CoordsSource::Manual->value]) ?>
    </div>
</section>

<!-- Шаг 3–4. Порода и дата -->
<section class="card mb-3 planting-step">
    <div class="card-body">
        <h2 class="h5"><span class="step-num"><?= $isCreate ? 3 : 2 ?></span> Порода и дата</h2>
        <div class="row">
            <div class="col-md-7">
                <input type="search" class="form-control form-control-sm mb-1" placeholder="Поиск породы…" data-species-search aria-label="Поиск породы">
                <?= $form->field($model, 'species_id')->dropDownList($speciesList, [
                    'prompt' => '— выберите породу —',
                    'data-species-select' => true,
                    'size' => 1,
                ]) ?>
            </div>
            <div class="col-md-5">
                <?= $form->field($model, 'planted_at')->input('date', ['max' => date('Y-m-d')]) ?>
            </div>
        </div>
        <?= $form->field($model, 'title')->textInput(['maxlength' => 150]) ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 3, 'maxlength' => 5000]) ?>
    </div>
</section>

<!-- Шаг 5. Кто посадил -->
<section class="card mb-3 planting-step">
    <div class="card-body">
        <h2 class="h5"><span class="step-num"><?= $isCreate ? 4 : 3 ?></span> Кто посадил</h2>
        <?= $form->field($model, 'links', ['template' => "{hint}\n{input}\n{error}"])->widget(LinksInputWidget::class) ?>
    </div>
</section>

<!-- Шаг 6. Сохранение -->
<div class="d-flex flex-wrap gap-2 mb-5 planting-actions">
    <?php if ($isPublished): ?>
        <?= Html::submitButton('<i class="bi bi-send"></i> Отправить изменения на модерацию', [
            'class' => 'btn btn-success btn-lg', 'name' => 'PlantingForm[submit]', 'value' => 1, 'data-requires-valid' => true,
        ]) ?>
    <?php else: ?>
        <?= Html::submitButton('<i class="bi bi-send"></i> Отправить на модерацию', [
            'class' => 'btn btn-success btn-lg', 'name' => 'PlantingForm[submit]', 'value' => 1, 'data-requires-valid' => true,
        ]) ?>
        <?= Html::submitButton('Сохранить черновик', [
            'class' => 'btn btn-outline-secondary btn-lg', 'name' => 'PlantingForm[submit]', 'value' => 0, 'data-requires-valid' => true,
        ]) ?>
    <?php endif ?>
    <div class="small text-muted align-self-center" data-valid-hint>Для сохранения укажите место на карте и породу.</div>
</div>

<?php ActiveForm::end() ?>
