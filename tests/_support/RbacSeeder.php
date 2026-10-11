<?php

namespace tests\_support;

use app\rbac\OwnPlantingRule;
use app\rbac\Rbac;
use Yii;

/**
 * Инициализация RBAC в тестах и назначение ролей пользователям фикстур.
 */
final class RbacSeeder
{
    /**
     * @param array<int, string> $assignments [userId => role]
     */
    public static function seed(array $assignments = [1 => Rbac::ROLE_USER, 2 => Rbac::ROLE_USER, 3 => Rbac::ROLE_MODERATOR, 4 => Rbac::ROLE_ADMIN]): void
    {
        $auth = Yii::$app->authManager;
        $auth->removeAll();

        $rule = new OwnPlantingRule();
        $auth->add($rule);
        $perms = [];
        foreach (Rbac::PERMISSIONS as $name => $description) {
            $p = $auth->createPermission($name);
            $p->description = $description;
            if (str_ends_with($name, 'Own')) {
                $p->ruleName = $rule->name;
            }
            $auth->add($p);
            $perms[$name] = $p;
        }
        $user = $auth->createRole(Rbac::ROLE_USER);
        $auth->add($user);
        foreach ([Rbac::PLANTING_CREATE, Rbac::PLANTING_UPDATE_OWN, Rbac::PLANTING_DELETE_OWN, Rbac::PHOTO_UPLOAD_OWN, Rbac::PAYMENT_CREATE_OWN] as $n) {
            $auth->addChild($user, $perms[$n]);
        }
        $moderator = $auth->createRole(Rbac::ROLE_MODERATOR);
        $auth->add($moderator);
        $auth->addChild($moderator, $user);
        $auth->addChild($moderator, $perms[Rbac::MODERATE]);
        $admin = $auth->createRole(Rbac::ROLE_ADMIN);
        $auth->add($admin);
        $auth->addChild($admin, $moderator);
        foreach ([Rbac::MANAGE_SPECIES, Rbac::MANAGE_USERS, Rbac::MANAGE_ADS, Rbac::MANAGE_PAYMENTS, Rbac::MANAGE_SETTINGS] as $n) {
            $auth->addChild($admin, $perms[$n]);
        }

        foreach ($assignments as $userId => $role) {
            $auth->assign($auth->getRole($role), $userId);
        }
    }
}
