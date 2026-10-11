<?php

/** @var yii\web\View $this */
/** @var app\forms\RoleForm $model */
/** @var app\services\AccessService $access */

$label = $access->roleLabel($model->oldName);
$this->title = 'Роль: ' . $label;
$this->params['breadcrumbs'][] = ['label' => 'Права доступа', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $label, 'url' => ['view', 'name' => $model->oldName]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?php if ($model->isSystem()): ?>
    <div class="alert alert-info small">Системную роль нельзя переименовать или удалить, но можно изменить её название и разрешения.</div>
<?php endif ?>
<?= $this->render('_form', ['model' => $model, 'access' => $access]) ?>
