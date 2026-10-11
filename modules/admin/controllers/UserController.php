<?php

namespace app\modules\admin\controllers;

use app\enums\UserStatus;
use app\forms\UserAdminForm;
use app\models\ModerationLog;
use app\models\Payment;
use app\models\Planting;
use app\models\search\UserSearch;
use app\models\User;
use app\rbac\Rbac;
use app\services\AccessService;
use DomainException;
use Yii;
use yii\web\Response;

/**
 * Пользователи: список, создание, редактирование, блокировка, подтверждение контактов,
 * роли и отдельные разрешения, сброс сессий, удаление.
 */
class UserController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_USERS;
    protected array $postActions = ['block', 'unblock', 'access', 'delete', 'logout-everywhere'];

    public function __construct($id, $module, private readonly AccessService $access, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $search = new UserSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'dataProvider' => $search->search(Yii::$app->request->queryParams),
            'roles' => array_map(fn($r) => $this->access->roleLabel($r->name), $this->access->getRoles()),
        ]);
    }

    public function actionView(int $id): string
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        return $this->render('view', [
            'user' => $user,
            'access' => $this->access,
            'userRoles' => $this->access->getUserRoles($user->id),
            'directPermissions' => $this->access->getUserDirectPermissions($user->id),
            'effectivePermissions' => $this->access->getUserEffectivePermissions($user->id),
            'plantingsCount' => (int)Planting::find()->byOwner($user->id)->count(),
            'publishedCount' => (int)Planting::find()->byOwner($user->id)->published()->count(),
            'paymentsCount' => (int)Payment::find()->byUser($user->id)->count(),
        ]);
    }

    public function actionCreate(): Response|string
    {
        $form = new UserAdminForm(new User());
        $form->roles = [Rbac::ROLE_USER];

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $tx = Yii::$app->db->beginTransaction();
            try {
                if ($form->saveUser()) {
                    $this->access->setUserAccess($form->getUser(), $form->getRoleList(), $form->getPermissionList(), $this->moderator());
                    $tx->commit();
                    Yii::$app->session->setFlash('success', 'Пользователь создан.');
                    return $this->redirect(['view', 'id' => $form->getUser()->id]);
                }
                $tx->rollBack();
            } catch (DomainException $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('create', ['model' => $form, 'access' => $this->access]);
    }

    public function actionUpdate(int $id): Response|string
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        $form = new UserAdminForm($user);
        $form->roles = $this->access->getUserRoles($user->id);
        $form->permissions = $this->access->getUserDirectPermissions($user->id);
        $isSelf = (int)$user->id === (int)Yii::$app->user->id;

        if ($form->load(Yii::$app->request->post())) {
            if ($isSelf && $form->status !== UserStatus::Active->value) {
                $form->addError('status', 'Нельзя заблокировать или деактивировать собственную учётную запись.');
            } elseif ($form->validate()) {
                $tx = Yii::$app->db->beginTransaction();
                try {
                    if ($form->saveUser()) {
                        $this->access->setUserAccess($user, $form->getRoleList(), $form->getPermissionList(), $this->moderator());
                        $tx->commit();
                        Yii::$app->session->setFlash('success', 'Изменения сохранены.');
                        return $this->redirect(['view', 'id' => $user->id]);
                    }
                    $tx->rollBack();
                } catch (DomainException $e) {
                    $tx->rollBack();
                    $user->refresh();
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }
            }
        }
        return $this->render('update', ['model' => $form, 'access' => $this->access, 'isSelf' => $isSelf]);
    }

    /**
     * Быстрое изменение ролей и разрешений со страницы пользователя.
     */
    public function actionAccess(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        $post = Yii::$app->request->post();
        try {
            $this->access->setUserAccess($user, (array)($post['roles'] ?? []), (array)($post['permissions'] ?? []), $this->moderator());
            Yii::$app->session->setFlash('success', 'Права доступа обновлены.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionBlock(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        if ((int)$user->id === (int)Yii::$app->user->id) {
            Yii::$app->session->setFlash('error', 'Нельзя заблокировать самого себя.');
            return $this->redirect(['view', 'id' => $id]);
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $user->status = UserStatus::Blocked->value;
            $user->generateAuthKey();
            $user->save(false, ['status', 'auth_key', 'updated_at']);
            if (Yii::$app->authManager->checkAccess($user->id, Rbac::MANAGE_USERS)
                && $this->access->countUsersWithPermission(Rbac::MANAGE_USERS) < 1) {
                throw new DomainException('Нельзя заблокировать последнего администратора.');
            }
            $tx->commit();
            Yii::$app->session->setFlash('success', 'Пользователь заблокирован.');
        } catch (DomainException $e) {
            $tx->rollBack();
            Yii::$app->session->setFlash('error', $e->getMessage());
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
     * Завершить все сессии «запомнить меня» (смена auth_key).
     */
    public function actionLogoutEverywhere(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        $user->generateAuthKey();
        $user->save(false, ['auth_key', 'updated_at']);
        Yii::$app->session->setFlash('success', 'Сохранённые входы пользователя сброшены.');
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Удаление — только для учётных записей без посадок, платежей и решений модерации.
     * Иначе пользователя следует заблокировать.
     */
    public function actionDelete(int $id): Response
    {
        /** @var User $user */
        $user = $this->findModel(User::class, $id);
        if ((int)$user->id === (int)Yii::$app->user->id) {
            Yii::$app->session->setFlash('error', 'Нельзя удалить самого себя.');
            return $this->redirect(['view', 'id' => $id]);
        }
        $hasData = Planting::find()->where(['user_id' => $user->id])->exists()
            || Payment::find()->where(['user_id' => $user->id])->exists()
            || ModerationLog::find()->where(['moderator_id' => $user->id])->exists();
        if ($hasData) {
            Yii::$app->session->setFlash('error', 'У пользователя есть посадки, платежи или решения модерации — удалить нельзя. Заблокируйте его.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $wasAdmin = Yii::$app->authManager->checkAccess($user->id, Rbac::MANAGE_USERS);
            Yii::$app->authManager->revokeAll($user->id);
            $user->delete();
            if ($wasAdmin && $this->access->countUsersWithPermission(Rbac::MANAGE_USERS) < 1) {
                throw new DomainException('Нельзя удалить последнего администратора.');
            }
            $tx->commit();
            Yii::$app->session->setFlash('success', 'Пользователь удалён.');
            return $this->redirect(['index']);
        } catch (DomainException $e) {
            $tx->rollBack();
            Yii::$app->authManager->invalidateCache();
            Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['view', 'id' => $id]);
        }
    }
}
