<?php

use app\services\AccessService;
use yii\bootstrap5\Html;
use yii\rbac\Permission;
use yii\rbac\Role;

/** @var yii\web\View $this */
/** @var AccessService $access */
/** @var Role[] $roles */
/** @var Permission[] $permissions */
/** @var array<string, string[]> $direct */
/** @var array<string, string[]> $effective */
/** @var array<string, int> $counts */

$this->title = 'Права доступа';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <div class="d-flex gap-2">
        <?= Html::a('<i class="bi bi-people"></i> Пользователи', ['/admin/user/index'], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('<i class="bi bi-plus-lg"></i> Новая роль', ['create'], ['class' => 'btn btn-success']) ?>
    </div>
</div>

<h2 class="h5">Роли</h2>
<div class="row g-3 mb-4">
    <?php foreach ($roles as $name => $role): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h3 class="h6 mb-1"><?= Html::a(Html::encode($access->roleLabel($name)), ['view', 'name' => $name]) ?></h3>
                        <?php if ($access->isSystemRole($name)): ?><span class="badge text-bg-light">системная</span><?php endif ?>
                    </div>
                    <code class="small"><?= Html::encode($name) ?></code>
                    <div class="small text-muted mt-2">
                        Пользователей: <?= Html::a((string)(int)($counts[$name] ?? 0), ['/admin/user/index', 'UserSearch[role]' => $name]) ?> ·
                        разрешений: <?= count($effective[$name]) ?>
                    </div>
                    <?php if ($inh = $access->getChildRoles($name)): ?>
                        <div class="small text-muted">Включает: <?= Html::encode(implode(', ', array_map([$access, 'roleLabel'], $inh))) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<h2 class="h5">Разрешения ролей</h2>
<p class="small text-muted">
    <i class="bi bi-check-square"></i> — разрешение выдано роли напрямую;
    <i class="bi bi-check2 text-success"></i> — получено через включённую роль (меняется в настройках той роли).
    Разрешения с пометкой «только свои» действуют лишь на собственные карточки пользователя.
</p>
<?= Html::beginForm(['matrix'], 'post', ['id' => 'matrix-form']) ?>
<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle access-matrix">
        <thead class="table-light">
        <tr>
            <th>Разрешение</th>
            <?php foreach ($roles as $name => $role): ?>
                <th class="text-center"><?= Html::encode($access->roleLabel($name)) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($permissions as $pName => $perm): ?>
            <tr>
                <td>
                    <?= Html::encode($perm->description ?: $pName) ?>
                    <?php if ($perm->ruleName): ?><span class="badge text-bg-light">только свои</span><?php endif ?>
                    <div><code class="small text-muted"><?= Html::encode($pName) ?></code></div>
                </td>
                <?php foreach ($roles as $rName => $role): ?>
                    <?php
                    $isDirect = in_array($pName, $direct[$rName], true);
                    $isInherited = !$isDirect && in_array($pName, $effective[$rName], true);
                    ?>
                    <td class="text-center">
                        <?php if ($isInherited): ?>
                            <i class="bi bi-check2 text-success" title="Через включённую роль"></i>
                        <?php else: ?>
                            <?= Html::checkbox("matrix[{$rName}][]", $isDirect, [
                                'value' => $pName,
                                'class' => 'form-check-input',
                                'aria-label' => $access->roleLabel($rName) . ': ' . ($perm->description ?: $pName),
                            ]) ?>
                        <?php endif ?>
                    </td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= Html::submitButton('Сохранить разрешения', ['class' => 'btn btn-primary', 'data-confirm' => 'Изменить разрешения ролей? Это сразу повлияет на всех пользователей с этими ролями.']) ?>
<?= Html::endForm() ?>
