<?php

namespace app\modules\admin\controllers;

use app\enums\UserStatus;
use app\models\Planting;
use app\models\search\UserSearch;
use app\models\User;
use app\rbac\Rbac;
use Yii;
use yii\web\Response;

/**
 * Пользователи: список, просмотр, блокировка, назначение ролей.
 */
class UserController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_USERS;
    protected array $postActions = ['block', 'unblock', 'roles'];

    public function actionIndex(): string
    {
        $search = new UserSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
        ]);
    }

    public function actionView(int $id): string
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        return $this->render('view', [
            'user' => $user,
            'roles' => $user->getRoles(),
            'plantingsCount' => (int)Planting::find()->byOwner($user->id)->count(),
            'publishedCount' => (int)Planting::find()->byOwner($user->id)->published()->count(),
        ]);
    }

    public function actionBlock(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        if ((int)$user->id === (int)Yii::$app->user->id) {
            Yii::$app->session->setFlash('error', 'Нельзя заблокировать самого себя.');
        } else {
            $user->status = UserStatus::Blocked->value;
            $user->generateAuthKey(); // сброс «запомнить меня»
            $user->save(false, ['status', 'auth_key', 'updated_at']);
            Yii::$app->session->setFlash('success', 'Пользователь заблокирован.');
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionUnblock(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        $user->status = ($user->isEmailVerified() || $user->isPhoneVerified())
            ? UserStatus::Active->value
            : UserStatus::Unverified->value;
        $user->save(false, ['status', 'updated_at']);
        Yii::$app->session->setFlash('success', 'Пользователь разблокирован.');
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Назначение ролей: POST roles[] = user|moderator|admin.
     */
    public function actionRoles(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        $auth = Yii::$app->authManager;
        $requested = array_intersect((array)Yii::$app->request->post('roles', []), array_keys(Rbac::ROLES));

        if ((int)$user->id === (int)Yii::$app->user->id && !in_array(Rbac::ROLE_ADMIN, $requested, true)) {
            Yii::$app->session->setFlash('error', 'Нельзя снять с себя роль администратора.');
            return $this->redirect(['view', 'id' => $id]);
        }
        foreach (array_keys(Rbac::ROLES) as $roleName) {
            $role = $auth->getRole($roleName);
            $has = $auth->getAssignment($roleName, $user->id) !== null;
            $want = in_array($roleName, $requested, true);
            if ($want && !$has) {
                $auth->assign($role, $user->id);
            } elseif (!$want && $has) {
                $auth->revoke($role, $user->id);
            }
        }
        Yii::$app->session->setFlash('success', 'Роли обновлены.');
        return $this->redirect(['view', 'id' => $id]);
    }
}
