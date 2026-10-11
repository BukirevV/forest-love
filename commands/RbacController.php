<?php

namespace app\commands;

use app\rbac\OwnPlantingRule;
use app\rbac\Rbac;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Инициализация RBAC: роли, разрешения, правила (ТЗ 15).
 */
class RbacController extends Controller
{
    /**
     * Создаёт (пересоздаёт) иерархию ролей и разрешений. Назначения пользователей сохраняются.
     */
    public function actionInit(): int
    {
        $auth = Yii::$app->authManager;

        // Сохраняем назначения, чтобы не потерять при пересоздании
        $assignments = [];
        foreach (array_keys(Rbac::ROLES) as $roleName) {
            foreach ($auth->getUserIdsByRole($roleName) as $userId) {
                $assignments[] = [$roleName, $userId];
            }
        }
        $auth->removeAll();

        $ownRule = new OwnPlantingRule();
        $auth->add($ownRule);

        $perms = [];
        foreach (Rbac::PERMISSIONS as $name => $description) {
            $p = $auth->createPermission($name);
            $p->description = $description;
            if (in_array($name, [Rbac::PLANTING_UPDATE_OWN, Rbac::PLANTING_DELETE_OWN, Rbac::PHOTO_UPLOAD_OWN, Rbac::PAYMENT_CREATE_OWN], true)) {
                $p->ruleName = $ownRule->name;
            }
            $auth->add($p);
            $perms[$name] = $p;
        }

        $user = $auth->createRole(Rbac::ROLE_USER);
        $user->description = Rbac::ROLES[Rbac::ROLE_USER];
        $auth->add($user);
        foreach ([Rbac::PLANTING_CREATE, Rbac::PLANTING_UPDATE_OWN, Rbac::PLANTING_DELETE_OWN, Rbac::PHOTO_UPLOAD_OWN, Rbac::PAYMENT_CREATE_OWN] as $n) {
            $auth->addChild($user, $perms[$n]);
        }

        $moderator = $auth->createRole(Rbac::ROLE_MODERATOR);
        $moderator->description = Rbac::ROLES[Rbac::ROLE_MODERATOR];
        $auth->add($moderator);
        $auth->addChild($moderator, $user);
        $auth->addChild($moderator, $perms[Rbac::MODERATE]);

        $admin = $auth->createRole(Rbac::ROLE_ADMIN);
        $admin->description = Rbac::ROLES[Rbac::ROLE_ADMIN];
        $auth->add($admin);
        $auth->addChild($admin, $moderator);
        foreach ([Rbac::MANAGE_SPECIES, Rbac::MANAGE_USERS, Rbac::MANAGE_ADS, Rbac::MANAGE_PAYMENTS, Rbac::MANAGE_SETTINGS] as $n) {
            $auth->addChild($admin, $perms[$n]);
        }

        foreach ($assignments as [$roleName, $userId]) {
            $auth->assign($auth->getRole($roleName), $userId);
        }

        $this->stdout("RBAC инициализирован: роли user, moderator, admin; восстановлено назначений: " . count($assignments) . "\n");
        return ExitCode::OK;
    }
}
