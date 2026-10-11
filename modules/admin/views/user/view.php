<?php

use app\enums\UserStatus;
use app\models\User;
use app\services\AccessService;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var User $user */
/** @var AccessService $access */
/** @var string[] $userRoles */
/** @var string[] $directPermissions */
/** @var string[] $effectivePermissions */
/** @var int $plantingsCount */
/** @var int $publishedCount */
/** @var int $paymentsCount */

$this->title = $user->getPrivateName();
$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$isSelf = (int)$user->id === (int)Yii::$app->user->id;
$permissions = $access->getPermissions();
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $user->status, 'enum' => UserStatus::class]) ?>
    <?php if ($isSelf): ?><span class="badge text-bg-info">это вы</span><?php endif ?>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?= Html::a('<i class="bi bi-pencil"></i> Редактировать', ['update', 'id' => $user->id], ['class' => 'btn btn-primary']) ?>
        <?php if (!$isSelf): ?>
            <?php if ($user->isBlocked()): ?>
                <?= Html::a('Разблокировать', ['unblock', 'id' => $user->id], ['class' => 'btn btn-outline-success', 'data-method' => 'post']) ?>
            <?php else: ?>
                <?= Html::a('Заблокировать', ['block', 'id' => $user->id], ['class' => 'btn btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Заблокировать пользователя? Он не сможет войти.']) ?>
            <?php endif ?>
        <?php endif ?>
        <?= Html::a('Сбросить сохранённые входы', ['logout-everywhere', 'id' => $user->id], ['class' => 'btn btn-outline-secondary', 'data-method' => 'post', 'data-confirm' => 'Пользователю придётся заново войти на всех устройствах. Продолжить?']) ?>
        <?php if (!$isSelf && !$plantingsCount && !$paymentsCount): ?>
            <?= Html::a('<i class="bi bi-trash"></i>', ['delete', 'id' => $user->id], ['class' => 'btn btn-outline-danger', 'title' => 'Удалить', 'data-method' => 'post', 'data-confirm' => 'Удалить пользователя безвозвратно?']) ?>
        <?php endif ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <?= DetailView::widget(['model' => $user, 'attributes' => [
            'id',
            'display_name',
            'email:email',
            'email_verified_at:datetime',
            'phone',
            'phone_verified_at:datetime',
            'created_at:datetime',
            'last_login_at:datetime',
            ['label' => 'Посадок (опубликовано)', 'format' => 'raw',
                'value' => Html::a("{$plantingsCount} ({$publishedCount})", ['/admin/planting/index', 'PlantingSearch[user_id]' => $user->id])],
            ['label' => 'Платежей', 'format' => 'raw',
                'value' => Html::a((string)$paymentsCount, ['/admin/payment/index', 'PaymentSearch[user_id]' => $user->id])],
        ]]) ?>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6">Права доступа</h2>
            <?= Html::beginForm(['access', 'id' => $user->id], 'post', ['id' => 'access-form']) ?>
                <?= $this->render('_access_fields', [
                    'access' => $access,
                    'selectedRoles' => $userRoles,
                    'selectedPermissions' => $directPermissions,
                    'rolesName' => 'roles',
                    'permissionsName' => 'permissions',
                ]) ?>
                <?= Html::submitButton('Сохранить права', ['class' => 'btn btn-primary mt-3']) ?>
            <?= Html::endForm() ?>
        </div></div>

        <div class="card"><div class="card-body">
            <h2 class="h6">Итоговые разрешения</h2>
            <?php if (!$effectivePermissions): ?>
                <p class="text-muted small mb-0">Нет разрешений — пользователь не может создавать карточки.</p>
            <?php else: ?>
                <ul class="small mb-0">
                    <?php foreach ($effectivePermissions as $p): ?>
                        <li><?= Html::encode(isset($permissions[$p]) ? ($permissions[$p]->description ?: $p) : $p) ?>
                            <code class="text-muted"><?= Html::encode($p) ?></code></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div></div>
    </div>
</div>
