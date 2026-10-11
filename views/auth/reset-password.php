<?php

use app\forms\ResetPasswordForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var ResetPasswordForm $model */

$this->title = 'Новый пароль';
$byCode = $model->scenario === ResetPasswordForm::SCENARIO_CODE;
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(['id' => 'reset-password-form']) ?>
    <?php if ($byCode): ?>
        <?= $form->field($model, 'phone')->textInput(['type' => 'tel']) ?>
        <?= $form->field($model, 'code')->textInput(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code']) ?>
    <?php else: ?>
        <?= Html::activeHiddenInput($model, 'token') ?>
    <?php endif ?>
    <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password', 'autofocus' => !$byCode]) ?>
    <?= $form->field($model, 'password_repeat')->passwordInput(['autocomplete' => 'new-password']) ?>
    <?= Html::submitButton('Сохранить пароль', ['class' => 'btn btn-success w-100']) ?>
<?php ActiveForm::end() ?>
