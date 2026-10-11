<?php

/** @var yii\web\View $this */
/** @var app\forms\RoleForm $model */
/** @var app\services\AccessService $access */

$this->title = 'Новая роль';
$this->params['breadcrumbs'][] = ['label' => 'Права доступа', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'access' => $access]) ?>
