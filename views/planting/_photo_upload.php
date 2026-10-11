<?php

use app\forms\PhotoUploadForm;
use app\models\Planting;
use app\widgets\PhotoCaptureWidget;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\bootstrap5\Modal;

/** @var yii\web\View $this */
/** @var Planting $planting */
/** @var PhotoUploadForm $model */

\app\assets\PlantingFormAsset::register($this);

Modal::begin([
    'id' => 'photo-upload-modal',
    'title' => 'Новая фотография',
    'options' => ['tabindex' => false],
]);
$form = ActiveForm::begin([
    'action' => ['/photo/upload', 'planting_id' => $planting->id],
    'options' => ['enctype' => 'multipart/form-data', 'data-photo-upload-form' => true],
]);
?>
    <p class="small text-muted">Новые фото дополняют историю роста дерева и проходят модерацию.</p>
    <?= $form->field($model, 'file', ['template' => "{input}\n{error}"])->widget(PhotoCaptureWidget::class, [
        'takenAtInputId' => Html::getInputId($model, 'taken_at'),
        'sourceInputId' => Html::getInputId($model, 'source'),
    ]) ?>
    <?= Html::activeHiddenInput($model, 'source') ?>
    <?= $form->field($model, 'taken_at')->input('date', ['max' => date('Y-m-d')])
        ->hint('Подставляется из фото; если в фото нет даты — укажите вручную.') ?>
    <?= Html::submitButton('Загрузить', ['class' => 'btn btn-success w-100']) ?>
<?php
ActiveForm::end();
Modal::end();
