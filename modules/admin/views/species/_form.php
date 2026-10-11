<?php

use app\models\TreeSpecies;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var TreeSpecies $model */
?>
<?php $form = ActiveForm::begin() ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'name')->textInput(['maxlength' => 150]) ?></div>
    <div class="col-md-6"><?= $form->field($model, 'latin_name')->textInput(['maxlength' => 150]) ?></div>
</div>
<?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'icon')->textInput()->hint('Путь к иконке или CSS-класс') ?></div>
    <div class="col-md-3"><?= $form->field($model, 'sort')->input('number') ?></div>
    <div class="col-md-3 pt-4"><?= $form->field($model, 'is_active')->checkbox() ?></div>
</div>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end() ?>
