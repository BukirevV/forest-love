<?php

namespace app\commands;

use app\enums\UserStatus;
use app\models\User;
use app\rbac\Rbac;
use app\validators\PhoneValidator;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Управление пользователями из консоли.
 */
class UserController extends Controller
{
    /**
     * Создание администратора: yii user/create-admin <email|phone> <password>
     */
    public function actionCreateAdmin(string $login, string $password): int
    {
        $user = User::findByLogin($login) ?? new User();
        if (str_contains($login, '@')) {
            $user->email = mb_strtolower($login);
            $user->email_verified_at ??= time();
        } else {
            $user->phone = PhoneValidator::normalize($login);
            $user->phone_verified_at ??= time();
            if (!$user->phone) {
                $this->stderr("Некорректный телефон.\n");
                return ExitCode::DATAERR;
            }
        }
        $user->status = UserStatus::Active->value;
        $user->display_name ??= 'Администратор';
        $user->setPassword($password);
        if ($user->isNewRecord) {
            $user->generateAuthKey();
        }
        if (!$user->save()) {
            $this->stderr(implode("\n", $user->getFirstErrors()) . "\n");
            return ExitCode::DATAERR;
        }
        return $this->assign($user, Rbac::ROLE_ADMIN);
    }

    /**
     * Назначение роли: yii user/assign-role <id> <user|moderator|admin>
     */
    public function actionAssignRole(int $id, string $role): int
    {
        $user = User::findOne($id);
        if (!$user) {
            $this->stderr("Пользователь #{$id} не найден.\n");
            return ExitCode::DATAERR;
        }
        return $this->assign($user, $role);
    }

    /**
     * Отзыв роли: yii user/revoke-role <id> <role>
     */
    public function actionRevokeRole(int $id, string $role): int
    {
        $auth = Yii::$app->authManager;
        $item = $auth->getRole($role);
        if (!$item) {
            $this->stderr("Роль {$role} не найдена.\n");
            return ExitCode::DATAERR;
        }
        $auth->revoke($item, $id);
        $this->stdout("Роль {$role} отозвана у #{$id}.\n");
        return ExitCode::OK;
    }

    private function assign(User $user, string $role): int
    {
        $auth = Yii::$app->authManager;
        $item = $auth->getRole($role);
        if (!$item) {
            $this->stderr("Роль {$role} не найдена. Выполните yii rbac/init.\n");
            return ExitCode::DATAERR;
        }
        if (!$auth->getAssignment($role, $user->id)) {
            $auth->assign($item, $user->id);
        }
        $this->stdout("Пользователю #{$user->id} назначена роль {$role}.\n");
        return ExitCode::OK;
    }
}
