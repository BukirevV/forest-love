<?php

use app\forms\SignupForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var SignupForm $model */

$this->title = 'Регистрация';
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
<p class="text-muted small">Укажите email или номер телефона. Мы отправим письмо или SMS с кодом подтверждения.</p>

<?php $form = ActiveForm::begin(['id' => 'signup-form']) ?>
    <?= $form->field($model, 'login')->textInput([
        'autofocus' => true,
        'autocomplete' => 'username',
        'placeholder' => 'name@example.com или +7 999 123-45-67',
    ]) ?>
    <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password']) ?>
    <?= $form->field($model, 'password_repeat')->passwordInput(['autocomplete' => 'new-password']) ?>
    <?= $form->field($model, 'agree')->checkbox([
        'label' => 'Я согласен(на) с ' . Html::a('политикой обработки персональных данных', ['/site/privacy'], ['target' => '_blank'])
            . ' и ' . Html::a('пользовательским соглашением', ['/site/terms'], ['target' => '_blank']),
    ]) ?>
    <?= Html::submitButton('Зарегистрироваться', ['class' => 'btn btn-success w-100']) ?>
<?php ActiveForm::end() ?>

<p class="text-center mt-3 mb-0 small">Уже зарегистрированы? <?= Html::a('Войти', ['login']) ?></p>
