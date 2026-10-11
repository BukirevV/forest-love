<?php

use app\services\AccessService;
use yii\bootstrap5\Html;

/**
 * Чекбоксы ролей и отдельных разрешений.
 *
 * @var AccessService $access
 * @var string[] $selectedRoles
 * @var string[] $selectedPermissions
 * @var string $rolesName
 * @var string $permissionsName
 */
?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="fw-semibold mb-1">Роли</div>
        <?= Html::hiddenInput($rolesName, '') ?>
        <?php foreach ($access->getRoles() as $name => $role): ?>
            <div class="form-check">
                <?= Html::checkbox($rolesName . '[]', in_array($name, $selectedRoles, true), [
                    'value' => $name, 'class' => 'form-check-input', 'id' => 'role-' . $name,
                ]) ?>
                <label class="form-check-label" for="role-<?= Html::encode($name) ?>">
                    <?= Html::encode($access->roleLabel($name)) ?>
                    <code class="small text-muted"><?= Html::encode($name) ?></code>
                </label>
            </div>
        <?php endforeach ?>
    </div>
    <div class="col-md-7">
        <div class="fw-semibold mb-1">Дополнительные разрешения</div>
        <div class="form-text mt-0 mb-1">Выдаются в дополнение к ролям. Обычно не нужны.</div>
        <?= Html::hiddenInput($permissionsName, '') ?>
        <?php foreach ($access->getPermissions() as $name => $perm): ?>
            <div class="form-check">
                <?= Html::checkbox($permissionsName . '[]', in_array($name, $selectedPermissions, true), [
                    'value' => $name, 'class' => 'form-check-input', 'id' => 'perm-' . $name,
                ]) ?>
                <label class="form-check-label small" for="perm-<?= Html::encode($name) ?>">
                    <?= Html::encode($perm->description ?: $name) ?>
                    <?php if ($perm->ruleName): ?><span class="badge text-bg-light">только свои</span><?php endif ?>
                </label>
            </div>
        <?php endforeach ?>
    </div>
</div>
