<?php

namespace app\commands;

use app\rbac\OwnPlantingRule;
use app\rbac\Rbac;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Инициализация RBAC: роли, разрешения, правила (ТЗ 15).
 *
 *   yii rbac/init          — создать недостающие системные роли/разрешения, ничего не удаляя
 *                            (роли и настройки, сделанные в админке, сохраняются);
 *   yii rbac/init --reset  — пересоздать всё с нуля (пользовательские роли и изменения
 *                            матрицы будут потеряны, назначения системных ролей сохраняются).
 */
class RbacController extends Controller
{
    public bool $reset = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['reset']);
    }

    public function actionInit(): int
    {
        $auth = Yii::$app->authManager;

        $assignments = [];
        if ($this->reset) {
            foreach (array_keys(Rbac::ROLES) as $roleName) {
                foreach ($auth->getUserIdsByRole($roleName) as $userId) {
                    $assignments[] = [$roleName, $userId];
                }
            }
            $auth->removeAll();
        }

        $rule = $auth->getRule('isPlantingOwner');
        if ($rule === null) {
            $rule = new OwnPlantingRule();
            $auth->add($rule);
        }

        $created = 0;
        $perms = [];
        foreach (Rbac::PERMISSIONS as $name => $description) {
            $p = $auth->getPermission($name);
            if ($p === null) {
                $p = $auth->createPermission($name);
                $p->description = $description;
                if (str_ends_with($name, 'Own')) {
                    $p->ruleName = $rule->name;
                }
                $auth->add($p);
                $created++;
            }
            $perms[$name] = $p;
        }

        $tree = [
            Rbac::ROLE_USER => ['children' => [], 'perms' => [Rbac::PLANTING_CREATE, Rbac::PLANTING_UPDATE_OWN, Rbac::PLANTING_DELETE_OWN, Rbac::PHOTO_UPLOAD_OWN, Rbac::PAYMENT_CREATE_OWN]],
            Rbac::ROLE_MODERATOR => ['children' => [Rbac::ROLE_USER], 'perms' => [Rbac::MODERATE]],
            Rbac::ROLE_ADMIN => ['children' => [Rbac::ROLE_MODERATOR], 'perms' => [Rbac::MANAGE_SPECIES, Rbac::MANAGE_USERS, Rbac::MANAGE_ADS, Rbac::MANAGE_PAYMENTS, Rbac::MANAGE_SETTINGS]],
        ];
        foreach ($tree as $roleName => $def) {
            $role = $auth->getRole($roleName);
            if ($role !== null) {
                continue; // существующую роль не трогаем — её могли настроить в админке
            }
            $role = $auth->createRole($roleName);
            $role->description = Rbac::ROLES[$roleName];
            $auth->add($role);
            foreach ($def['children'] as $child) {
                $auth->addChild($role, $auth->getRole($child));
            }
            foreach ($def['perms'] as $perm) {
                $auth->addChild($role, $perms[$perm]);
            }
            $created++;
        }

        // Гарантия от потери доступа: у admin всегда есть управление пользователями
        $admin = $auth->getRole(Rbac::ROLE_ADMIN);
        if (!isset($auth->getPermissionsByRole(Rbac::ROLE_ADMIN)[Rbac::MANAGE_USERS])) {
            $auth->addChild($admin, $perms[Rbac::MANAGE_USERS]);
        }

        foreach ($assignments as [$roleName, $userId]) {
            $auth->assign($auth->getRole($roleName), $userId);
        }
        $auth->invalidateCache();

        $this->stdout(($this->reset ? 'RBAC пересоздан' : 'RBAC проверен') . ": добавлено элементов: {$created}"
            . ($this->reset ? ', восстановлено назначений: ' . count($assignments) : '') . "\n");
        return ExitCode::OK;
    }
}
