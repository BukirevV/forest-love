<?php

use app\forms\RoleForm;
use app\services\AccessService;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var RoleForm $model */
/** @var AccessService $access */

$otherRoles = [];
foreach ($access->getRoles() as $name => $role) {
    if ($name !== $model->oldName) {
        $otherRoles[$name] = $access->roleLabel($name);
    }
}
$permissionItems = [];
foreach ($access->getPermissions() as $name => $perm) {
    $permissionItems[$name] = ($perm->description ?: $name) . ($perm->ruleName ? ' (только свои)' : '');
}
?>
<?php $form = ActiveForm::begin(['id' => 'role-form']) ?>
<div class="row g-4">
    <div class="col-lg-5">
        <?= $form->field($model, 'description')->textInput(['maxlength' => 200, 'placeholder' => 'Например: Менеджер рекламы']) ?>
        <?= $form->field($model, 'name')->textInput(['maxlength' => 64, 'readonly' => $model->isSystem()]) ?>
        <?= $form->field($model, 'inherits')->checkboxList($otherRoles) ?>
    </div>
    <div class="col-lg-7">
        <?= $form->field($model, 'permissions')->checkboxList($permissionItems) ?>
    </div>
</div>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?= Html::a('Отмена', $model->isNew() ? ['index'] : ['view', 'name' => $model->oldName], ['class' => 'btn btn-outline-secondary']) ?>
<?php ActiveForm::end() ?>
