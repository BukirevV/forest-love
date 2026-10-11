<?php

use app\enums\UserStatus;
use app\models\User;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\UserSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $roles [name => label] */

$this->title = 'Пользователи';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <div class="d-flex gap-2">
        <?= Html::a('<i class="bi bi-shield-lock"></i> Права доступа', ['/admin/access/index'], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('<i class="bi bi-person-plus"></i> Добавить пользователя', ['create'], ['class' => 'btn btn-success']) ?>
    </div>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'pager' => ['class' => LinkPager::class],
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        'id',
        [
            'attribute' => 'display_name',
            'format' => 'raw',
            'value' => static fn(User $u) => Html::a(Html::encode($u->display_name ?: '—'), ['view', 'id' => $u->id]),
        ],
        'email:email',
        'phone',
        [
            'attribute' => 'status',
            'filter' => UserStatus::labels(),
            'format' => 'raw',
            'value' => static fn(User $u) => StatusBadgeWidget::widget(['status' => $u->status, 'enum' => UserStatus::class]),
        ],
        [
            'attribute' => 'role',
            'label' => 'Роли',
            'filter' => $roles,
            'format' => 'raw',
            'value' => static fn(User $u) => implode(' ', array_map(
                static fn($r) => Html::tag('span', Html::encode($roles[$r] ?? $r), ['class' => 'badge ' . ($r === 'admin' ? 'text-bg-danger' : ($r === 'moderator' ? 'text-bg-primary' : 'text-bg-secondary'))]),
                $u->getRoles()
            )),
        ],
        [
            'attribute' => 'created_at',
            'format' => 'date',
            'filter' => Html::activeInput('date', $searchModel, 'registered_from', ['class' => 'form-control form-control-sm'])
                . Html::activeInput('date', $searchModel, 'registered_to', ['class' => 'form-control form-control-sm mt-1']),
        ],
        'last_login_at:relativeTime',
        [
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-nowrap'],
            'value' => static fn(User $u) => Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $u->id], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Открыть'])
                . ' ' . Html::a('<i class="bi bi-pencil"></i>', ['update', 'id' => $u->id], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Редактировать']),
        ],
    ],
]) ?>
