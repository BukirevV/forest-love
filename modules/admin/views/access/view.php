<?php

use app\enums\UserStatus;
use app\models\User;
use app\services\AccessService;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use yii\rbac\Role;

/** @var yii\web\View $this */
/** @var Role $role */
/** @var AccessService $access */
/** @var string[] $direct */
/** @var string[] $inherits */
/** @var string[] $effective */
/** @var yii\data\ActiveDataProvider $users */

$label = $access->roleLabel($role->name);
$this->title = 'Роль: ' . $label;
$this->params['breadcrumbs'][] = ['label' => 'Права доступа', 'url' => ['index']];
$this->params['breadcrumbs'][] = $label;
$permissions = $access->getPermissions();
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <code><?= Html::encode($role->name) ?></code>
    <?php if ($access->isSystemRole($role->name)): ?><span class="badge text-bg-light">системная</span><?php endif ?>
    <div class="ms-auto d-flex gap-2">
        <?= Html::a('<i class="bi bi-pencil"></i> Редактировать', ['update', 'name' => $role->name], ['class' => 'btn btn-primary']) ?>
        <?php if (!$access->isSystemRole($role->name)): ?>
            <?= Html::a('<i class="bi bi-trash"></i> Удалить', ['delete', 'name' => $role->name], [
                'class' => 'btn btn-outline-danger', 'data-method' => 'post',
                'data-confirm' => 'Удалить роль? Пользователи с этой ролью потеряют её разрешения.',
            ]) ?>
        <?php endif ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <?php if ($inherits): ?>
                <h2 class="h6">Включает роли</h2>
                <ul class="small">
                    <?php foreach ($inherits as $r): ?>
                        <li><?= Html::a(Html::encode($access->roleLabel($r)), ['view', 'name' => $r]) ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <h2 class="h6">Разрешения</h2>
            <ul class="small mb-0">
                <?php foreach ($effective as $p): ?>
                    <li>
                        <?= Html::encode(isset($permissions[$p]) ? ($permissions[$p]->description ?: $p) : $p) ?>
                        <?php if (!in_array($p, $direct, true)): ?><span class="text-muted">(через включённую роль)</span><?php endif ?>
                    </li>
                <?php endforeach ?>
                <?php if (!$effective): ?><li class="text-muted">Нет разрешений</li><?php endif ?>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-7">
        <h2 class="h6">Пользователи с этой ролью</h2>
        <?= GridView::widget([
            'dataProvider' => $users,
            'pager' => ['class' => LinkPager::class],
            'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
            'emptyText' => 'Роль никому не назначена.',
            'columns' => [
                'id',
                ['label' => 'Пользователь', 'format' => 'raw',
                    'value' => static fn(User $u) => Html::a(Html::encode($u->getPrivateName()), ['/admin/user/view', 'id' => $u->id])],
                ['attribute' => 'status', 'format' => 'raw',
                    'value' => static fn(User $u) => StatusBadgeWidget::widget(['status' => $u->status, 'enum' => UserStatus::class])],
                'last_login_at:relativeTime',
            ],
        ]) ?>
    </div>
</div>
