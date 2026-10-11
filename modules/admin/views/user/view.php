<?php

use app\enums\UserStatus;
use app\models\User;
use app\rbac\Rbac;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var User $user */
/** @var string[] $roles */
/** @var int $plantingsCount */
/** @var int $publishedCount */

$this->title = $user->getPrivateName();
$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $user->status, 'enum' => UserStatus::class]) ?>
    <div class="ms-auto">
        <?php if ($user->isBlocked()): ?>
            <?= Html::a('Разблокировать', ['unblock', 'id' => $user->id], ['class' => 'btn btn-outline-success', 'data-method' => 'post']) ?>
        <?php else: ?>
            <?= Html::a('Заблокировать', ['block', 'id' => $user->id], ['class' => 'btn btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Заблокировать пользователя?']) ?>
        <?php endif ?>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <?= DetailView::widget(['model' => $user, 'attributes' => [
            'id', 'email:email', 'email_verified_at:datetime', 'phone', 'phone_verified_at:datetime', 'display_name',
            'created_at:datetime', 'last_login_at:datetime',
            ['label' => 'Посадок (опубликовано)', 'format' => 'raw',
                'value' => Html::a("{$plantingsCount} ({$publishedCount})", ['/admin/planting/index', 'PlantingSearch[user_id]' => $user->id])],
        ]]) ?>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h2 class="h6">Роли</h2>
            <?= Html::beginForm(['roles', 'id' => $user->id]) ?>
                <?= Html::checkboxList('roles', $roles, Rbac::ROLES, ['itemOptions' => ['labelOptions' => ['class' => 'd-block']]]) ?>
                <?= Html::submitButton('Сохранить роли', ['class' => 'btn btn-primary mt-2']) ?>
            <?= Html::endForm() ?>
        </div></div>
    </div>
</div>
