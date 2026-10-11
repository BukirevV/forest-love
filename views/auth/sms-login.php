<?php

use app\forms\SmsLoginForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var SmsLoginForm $model */
/** @var bool $codeSent */

$this->title = 'Вход по SMS';
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(['id' => 'sms-login-form', 'enableClientValidation' => false]) ?>
    <?= Html::hiddenInput('step', $codeSent ? 'verify' : 'request') ?>
    <?= $form->field($model, 'phone')->textInput(['type' => 'tel', 'autocomplete' => 'tel', 'readonly' => $codeSent]) ?>
    <?php if ($codeSent): ?>
        <p class="small text-muted">Если номер зарегистрирован, на него отправлен код.</p>
        <?= $form->field($model, 'code')->textInput(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'autofocus' => true]) ?>
        <?= Html::submitButton('Войти', ['class' => 'btn btn-success w-100']) ?>
    <?php else: ?>
        <?= Html::submitButton('Получить код', ['class' => 'btn btn-success w-100']) ?>
    <?php endif ?>
<?php ActiveForm::end() ?>

<p class="text-center mt-3 small"><?= Html::a('Войти с паролем', ['login']) ?></p>
