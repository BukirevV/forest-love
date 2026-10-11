<?php

use app\forms\ProfileForm;
use app\forms\VerifyCodeForm;
use app\models\User;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var ProfileForm $model */
/** @var VerifyCodeForm $phoneForm */
/** @var User $user */

$this->title = 'Профиль';
?>
<h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>

<div class="card mb-4">
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => 'profile-form']) ?>
            <?= $form->field($model, 'display_name') ?>
            <?= $form->field($model, 'email', [
                'inputTemplate' => '{input}' . ($user->email ? Html::tag('div', $user->isEmailVerified()
                    ? '<i class="bi bi-patch-check text-success"></i> подтверждён'
                    : '<i class="bi bi-exclamation-circle text-warning"></i> не подтверждён', ['class' => 'form-text']) : ''),
            ])->input('email') ?>
            <?= $form->field($model, 'phone', [
                'inputTemplate' => '{input}' . ($user->phone ? Html::tag('div', $user->isPhoneVerified()
                    ? '<i class="bi bi-patch-check text-success"></i> подтверждён'
                    : '<i class="bi bi-exclamation-circle text-warning"></i> не подтверждён', ['class' => 'form-text']) : ''),
            ])->input('tel') ?>
            <p class="small text-muted">Новый email или телефон начнёт действовать после подтверждения. Контакты не отображаются публично.</p>

            <hr>
            <h2 class="h6">Смена пароля</h2>
            <div class="row">
                <div class="col-md-6"><?= $form->field($model, 'new_password')->passwordInput(['autocomplete' => 'new-password']) ?></div>
                <?php if ($user->password_hash): ?>
                    <div class="col-md-6"><?= $form->field($model, 'current_password')->passwordInput(['autocomplete' => 'current-password']) ?></div>
                <?php endif ?>
            </div>

            <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>

<?php if ($phoneForm->target): ?>
    <div class="card border-warning">
        <div class="card-body">
            <h2 class="h6">Подтверждение нового телефона</h2>
            <?php $f2 = ActiveForm::begin(['action' => ['/cabinet/confirm-phone'], 'id' => 'confirm-phone-form']) ?>
                <?= Html::activeHiddenInput($phoneForm, 'target') ?>
                <?= $f2->field($phoneForm, 'code')->textInput(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code']) ?>
                <?= Html::submitButton('Подтвердить', ['class' => 'btn btn-success']) ?>
            <?php ActiveForm::end() ?>
            <?= Html::beginForm(['/cabinet/resend-contact-code'], 'post', ['class' => 'mt-2']) ?>
                <?= Html::hiddenInput('target', $phoneForm->target) ?>
                <?= Html::submitButton('Отправить код ещё раз', ['class' => 'btn btn-link btn-sm p-0']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
<?php endif ?>
