<?php

namespace app\modules\admin\controllers;

use app\forms\RoleForm;
use app\models\User;
use app\rbac\Rbac;
use app\services\AccessService;
use DomainException;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Права доступа: роли, матрица «роль × разрешение», пользователи роли.
 */
class AccessController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_USERS;
    protected array $postActions = ['matrix', 'delete'];

    public function __construct($id, $module, private readonly AccessService $access, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * Список ролей и матрица разрешений.
     */
    public function actionIndex(): string
    {
        $roles = $this->access->getRoles();
        $direct = $effective = [];
        foreach ($roles as $name => $role) {
            $direct[$name] = $this->access->getDirectPermissions($name);
            $effective[$name] = $this->access->getEffectiveRolePermissions($name);
        }
        return $this->render('index', [
            'access' => $this->access,
            'roles' => $roles,
            'permissions' => $this->access->getPermissions(),
            'direct' => $direct,
            'effective' => $effective,
            'counts' => $this->access->getRoleUserCounts(),
        ]);
    }

    /**
     * Сохранение матрицы: POST matrix[role][] = permission.
     */
    public function actionMatrix(): Response
    {
        try {
            $this->access->saveMatrix((array)Yii::$app->request->post('matrix', []), $this->moderator());
            Yii::$app->session->setFlash('success', 'Разрешения ролей сохранены.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return $this->redirect(['index']);
    }

    public function actionCreate(): Response|string
    {
        $form = new RoleForm();
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $role = $this->access->saveRole(null, $form->name, $form->description, (array)$form->permissions, (array)$form->inherits, $this->moderator());
                Yii::$app->session->setFlash('success', 'Роль создана.');
                return $this->redirect(['view', 'name' => $role->name]);
            } catch (DomainException $e) {
                $form->addError('name', $e->getMessage());
            }
        }
        return $this->render('create', ['model' => $form, 'access' => $this->access]);
    }

    public function actionUpdate(string $name): Response|string
    {
        $role = Yii::$app->authManager->getRole($name) ?? throw new NotFoundHttpException('Роль не найдена.');
        $form = RoleForm::fromRole($role, $this->access->getDirectPermissions($name), $this->access->getChildRoles($name));

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $saved = $this->access->saveRole($name, $form->name, $form->description, (array)$form->permissions, (array)$form->inherits, $this->moderator());
                Yii::$app->session->setFlash('success', 'Роль сохранена.');
                return $this->redirect(['view', 'name' => $saved->name]);
            } catch (DomainException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->render('update', ['model' => $form, 'access' => $this->access]);
    }

    /**
     * Карточка роли: разрешения и пользователи с этой ролью.
     */
    public function actionView(string $name): string
    {
        $role = Yii::$app->authManager->getRole($name) ?? throw new NotFoundHttpException('Роль не найдена.');
        $userIds = Yii::$app->authManager->getUserIdsByRole($name);
        return $this->render('view', [
            'role' => $role,
            'access' => $this->access,
            'direct' => $this->access->getDirectPermissions($name),
            'inherits' => $this->access->getChildRoles($name),
            'effective' => $this->access->getEffectiveRolePermissions($name),
            'users' => new ActiveDataProvider([
                'query' => User::find()->where(['id' => $userIds ?: [0]]),
                'pagination' => ['pageSize' => 50],
                'sort' => ['defaultOrder' => ['id' => SORT_ASC]],
            ]),
        ]);
    }

    public function actionDelete(string $name): Response
    {
        try {
            $this->access->deleteRole($name, $this->moderator());
            Yii::$app->session->setFlash('success', 'Роль удалена.');
            return $this->redirect(['index']);
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['view', 'name' => $name]);
        }
    }
}
