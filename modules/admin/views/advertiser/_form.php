<?php

use app\models\Advertiser;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var Advertiser $model */
?>
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]) ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'name') ?></div>
    <div class="col-md-6"><?= $form->field($model, 'legal_name') ?></div>
</div>
<?= $form->field($model, 'legal_info')->textarea(['rows' => 4])->hint('ИНН, ОГРН, юридический адрес — для маркировки рекламы') ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'website_url')->input('url') ?></div>
    <div class="col-md-3"><?= $form->field($model, 'contact_email')->input('email') ?></div>
    <div class="col-md-3"><?= $form->field($model, 'contact_phone') ?></div>
</div>
<?php if ($model->getLogoUrl()): ?>
    <img src="<?= Html::encode($model->getLogoUrl()) ?>" alt="" style="max-height: 60px" class="mb-2">
<?php endif ?>
<?= $form->field($model, 'logoFile')->fileInput(['accept' => 'image/*']) ?>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end() ?>
