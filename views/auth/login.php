<?php

use app\forms\LoginForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var LoginForm $model */

$this->title = 'Вход';
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(['id' => 'login-form']) ?>
    <?= $form->field($model, 'login')->textInput(['autofocus' => true, 'autocomplete' => 'username']) ?>
    <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'current-password']) ?>
    <div class="d-flex justify-content-between align-items-center">
        <?= $form->field($model, 'rememberMe')->checkbox() ?>
        <?= Html::a('Забыли пароль?', ['request-password-reset'], ['class' => 'small mb-3']) ?>
    </div>
    <?= Html::submitButton('Войти', ['class' => 'btn btn-success w-100', 'name' => 'login-button']) ?>
<?php ActiveForm::end() ?>

<div class="text-center mt-3 small">
    <?= Html::a('Войти по коду из SMS', ['sms-login']) ?><br>
    Нет учётной записи? <?= Html::a('Зарегистрироваться', ['signup']) ?>
</div>
