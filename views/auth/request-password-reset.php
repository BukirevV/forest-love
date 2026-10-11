<?php

use app\forms\PasswordResetRequestForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PasswordResetRequestForm $model */

$this->title = 'Восстановление пароля';
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
<p class="small text-muted">Укажите email или телефон, указанный при регистрации. Мы отправим ссылку или код для установки нового пароля.</p>

<?php $form = ActiveForm::begin(['id' => 'request-password-reset-form']) ?>
    <?= $form->field($model, 'login')->textInput(['autofocus' => true]) ?>
    <?= Html::submitButton('Отправить', ['class' => 'btn btn-success w-100']) ?>
<?php ActiveForm::end() ?>

<p class="text-center mt-3 small"><?= Html::a('Вернуться ко входу', ['login']) ?></p>
