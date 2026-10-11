<?php

use app\enums\BannerPosition;
use app\enums\PlacementType;
use app\forms\AdPlacementForm;
use app\models\AdPlacement;
use app\widgets\MapWidget;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var AdPlacementForm $model */
/** @var AdPlacement $placement */
/** @var array $campaigns */

\app\assets\MapAsset::register($this);
$isPoint = $model->type === PlacementType::Point->value;
?>
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'data-placement-form' => true]]) ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'campaign_id')->dropDownList($campaigns, ['prompt' => '—']) ?></div>
    <div class="col-md-3"><?= $form->field($model, 'type')->dropDownList(PlacementType::labels(), ['data-placement-type' => true]) ?></div>
    <div class="col-md-3"><?= $form->field($model, 'priority')->input('number') ?></div>
</div>

<div data-placement-section="point" class="<?= $isPoint ? '' : 'd-none' ?>">
    <?= MapWidget::widget([
        'mode' => MapWidget::MODE_PICKER,
        'marker' => $model->latitude ? [(float)$model->latitude, (float)$model->longitude] : null,
        'latInputId' => Html::getInputId($model, 'latitude'),
        'lngInputId' => Html::getInputId($model, 'longitude'),
        'options' => ['class' => 'rounded mb-2', 'style' => 'height: 320px'],
    ]) ?>
    <div class="row">
        <div class="col-md-4"><?= $form->field($model, 'latitude') ?></div>
        <div class="col-md-4"><?= $form->field($model, 'longitude') ?></div>
        <div class="col-md-4">
            <?= $form->field($model, 'markerFile')->fileInput(['accept' => 'image/*']) ?>
            <?php if ($placement->getMarkerIconUrl()): ?><img src="<?= Html::encode($placement->getMarkerIconUrl()) ?>" height="32" alt=""><?php endif ?>
        </div>
    </div>
</div>

<div data-placement-section="banner" class="<?= $isPoint ? 'd-none' : '' ?>">
    <div class="row">
        <div class="col-md-4"><?= $form->field($model, 'position')->dropDownList(BannerPosition::labels(), ['prompt' => '—']) ?></div>
        <div class="col-md-4">
            <?= $form->field($model, 'imageFile')->fileInput(['accept' => 'image/*']) ?>
            <?php if ($placement->getImageUrl()): ?><img src="<?= Html::encode($placement->getImageUrl()) ?>" class="img-fluid" style="max-height:80px" alt=""><?php endif ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'imageMobileFile')->fileInput(['accept' => 'image/*']) ?>
            <?php if ($placement->image_mobile_path): ?><img src="<?= Html::encode($placement->getImageMobileUrl()) ?>" class="img-fluid" style="max-height:80px" alt=""><?php endif ?>
        </div>
    </div>
</div>

<?= $form->field($model, 'target_url')->input('url')->hint('Пусто — переход в карточку рекламы') ?>
<?= $form->field($model, 'is_active')->checkbox() ?>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end() ?>
