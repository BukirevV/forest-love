<?php

use app\enums\CampaignStatus;
use app\forms\AdCampaignForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var AdCampaignForm $model */
/** @var array $advertisers */
/** @var array $plantingOptions */
?>
<?php $form = ActiveForm::begin() ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'advertiser_id')->dropDownList($advertisers, ['prompt' => '—']) ?></div>
    <div class="col-md-6"><?= $form->field($model, 'status')->dropDownList(CampaignStatus::labels()) ?></div>
</div>
<div class="row">
    <div class="col-md-8"><?= $form->field($model, 'title') ?></div>
    <div class="col-md-4"><?= $form->field($model, 'slug')->hint('Пусто — сформируется из названия') ?></div>
</div>
<?= $form->field($model, 'marketing_text')->textarea(['rows' => 8])->hint('Разрешён базовый HTML: абзацы, списки, ссылки, выделение. Очищается HtmlPurifier.') ?>
<?= $form->field($model, 'legal_text')->textarea(['rows' => 3])->hint('Маркировка рекламы (erid), сведения о рекламодателе') ?>
<div class="row">
    <div class="col-md-4"><?= $form->field($model, 'starts_at')->input('date') ?></div>
    <div class="col-md-4"><?= $form->field($model, 'ends_at')->input('date') ?></div>
    <div class="col-md-4"><?= $form->field($model, 'funded_trees_target')->input('number', ['min' => 1]) ?></div>
</div>
<?= $form->field($model, 'planting_ids')->listBox($plantingOptions, ['multiple' => true, 'size' => 10])
    ->hint('Удерживайте Ctrl/Cmd для выбора нескольких. Удобнее выбирать на карте: «Привязать посадки на карте».') ?>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end() ?>
