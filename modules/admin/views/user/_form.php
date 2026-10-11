<?php

use app\enums\UserStatus;
use app\forms\UserAdminForm;
use app\services\AccessService;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var UserAdminForm $model */
/** @var AccessService $access */
/** @var bool $isSelf */

$isSelf ??= false;
?>
<?php $form = ActiveForm::begin(['id' => 'user-form']) ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h6">Учётная запись</h2>
            <?= $form->field($model, 'display_name') ?>
            <div class="row">
                <div class="col-sm-8"><?= $form->field($model, 'email')->input('email') ?></div>
                <div class="col-sm-4 pt-sm-4"><?= $form->field($model, 'email_verified')->checkbox() ?></div>
            </div>
            <div class="row">
                <div class="col-sm-8"><?= $form->field($model, 'phone')->input('tel') ?></div>
                <div class="col-sm-4 pt-sm-4"><?= $form->field($model, 'phone_verified')->checkbox() ?></div>
            </div>
            <?= $form->field($model, 'status')->dropDownList(UserStatus::labels(), ['disabled' => $isSelf]) ?>
            <?php if ($isSelf): ?>
                <?= Html::activeHiddenInput($model, 'status') ?>
                <div class="form-text mb-3">Свой статус изменить нельзя.</div>
            <?php endif ?>
            <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password']) ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h6">Права доступа</h2>
            <?= $this->render('_access_fields', [
                'access' => $access,
                'selectedRoles' => $model->getRoleList(),
                'selectedPermissions' => $model->getPermissionList(),
                'rolesName' => Html::getInputName($model, 'roles'),
                'permissionsName' => Html::getInputName($model, 'permissions'),
            ]) ?>
            <?= Html::error($model, 'roles', ['class' => 'text-danger small']) ?>
            <p class="small text-muted mt-3 mb-0">
                Состав ролей настраивается в разделе <?= Html::a('«Права доступа»', ['/admin/access/index']) ?>.
            </p>
        </div></div>
    </div>
</div>
<div class="mt-3">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    <?= Html::a('Отмена', $model->getUser()->isNewRecord ? ['index'] : ['view', 'id' => $model->getUser()->id], ['class' => 'btn btn-outline-secondary']) ?>
</div>
<?php ActiveForm::end() ?>
