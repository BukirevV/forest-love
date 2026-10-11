<?php

use app\forms\VerifyCodeForm;
use app\validators\PhoneValidator;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var VerifyCodeForm $model */
/** @var int $resendIn */

$this->title = 'Подтверждение телефона';
$length = (int)Yii::$app->params['auth.codeLength'];
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
<?php if ($model->target): ?>
    <p class="small text-muted">Код отправлен на номер <?= Html::encode(PhoneValidator::mask($model->target)) ?>.</p>
<?php endif ?>

<?php $form = ActiveForm::begin(['id' => 'verify-form']) ?>
    <?= $model->target
        ? Html::activeHiddenInput($model, 'target')
        : $form->field($model, 'target')->textInput(['type' => 'tel', 'autocomplete' => 'tel']) ?>
    <?= $form->field($model, 'code')->textInput([
        'autofocus' => true,
        'inputmode' => 'numeric',
        'autocomplete' => 'one-time-code',
        'maxlength' => $length,
        'pattern' => '\d{' . $length . '}',
        'class' => 'form-control form-control-lg text-center code-input',
    ]) ?>
    <?= Html::submitButton('Подтвердить', ['class' => 'btn btn-success w-100']) ?>
<?php ActiveForm::end() ?>

<?php if ($model->target): ?>
    <?= Html::beginForm(['resend-code'], 'post', ['class' => 'text-center mt-3', 'data-resend-form' => true]) ?>
        <?= Html::hiddenInput('target', $model->target) ?>
        <?= Html::hiddenInput('purpose', 'signup') ?>
        <?= Html::submitButton('Отправить код ещё раз', [
            'class' => 'btn btn-link btn-sm',
            'data-resend-in' => $resendIn,
            'disabled' => $resendIn > 0,
        ]) ?>
    <?= Html::endForm() ?>
<?php endif ?>
