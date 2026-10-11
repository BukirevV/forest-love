<?php

/** @var yii\web\View $this */
/** @var app\forms\UserAdminForm $model */
/** @var app\services\AccessService $access */

$this->title = 'Новый пользователь';
$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<p class="text-muted small">Пользователь, созданный администратором, может сразу входить по указанному email или телефону и паролю.</p>
<?= $this->render('_form', ['model' => $model, 'access' => $access]) ?>
