<?php

/** @var yii\web\View $this */
/** @var app\forms\UserAdminForm $model */
/** @var app\services\AccessService $access */
/** @var bool $isSelf */

$user = $model->getUser();
$this->title = 'Редактирование: ' . $user->getPrivateName();
$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $user->getPrivateName(), 'url' => ['view', 'id' => $user->id]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'access' => $access, 'isSelf' => $isSelf]) ?>
