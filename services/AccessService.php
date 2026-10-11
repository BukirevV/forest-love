<?php

namespace app\services;

use app\models\User;
use app\rbac\Rbac;
use DomainException;
use Yii;
use yii\rbac\DbManager;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;

/**
 * Управление ролями, разрешениями и назначениями пользователям.
 * Защищает от потери доступа: системные роли нельзя удалить, у роли admin нельзя
 * отнять управление пользователями, нельзя снять последнего администратора
 * и лишить себя доступа к этому разделу.
 */
class AccessService
{
    /** Разрешение, без которого нельзя попасть в раздел управления доступом. */
    public const LOCKOUT_PERMISSION = Rbac::MANAGE_USERS;

    private function auth(): DbManager
    {
        return Yii::$app->authManager;
    }

    // ---------------------------------------------------------------- Справочники

    /** @return Role[] [name => Role], системные роли первыми */
    public function getRoles(): array
    {
        $roles = $this->auth()->getRoles();
        uksort($roles, static function ($a, $b) {
            $order = array_flip(array_keys(Rbac::ROLES));
            return [($order[$a] ?? 99), $a] <=> [($order[$b] ?? 99), $b];
        });
        return $roles;
    }

    /** @return Permission[] в порядке Rbac::PERMISSIONS, затем прочие */
    public function getPermissions(): array
    {
        $all = $this->auth()->getPermissions();
        $ordered = [];
        foreach (array_keys(Rbac::PERMISSIONS) as $name) {
            if (isset($all[$name])) {
                $ordered[$name] = $all[$name];
                unset($all[$name]);
            }
        }
        return $ordered + $all;
    }

    public function isSystemRole(string $name): bool
    {
        return isset(Rbac::ROLES[$name]);
    }

    public function roleLabel(string $name): string
    {
        $role = $this->auth()->getRole($name);
        return Rbac::ROLES[$name] ?? ($role?->description ?: $name);
    }

    /** Разрешения, назначенные роли напрямую. @return string[] */
    public function getDirectPermissions(string $roleName): array
    {
        $result = [];
        foreach ($this->auth()->getChildren($roleName) as $child) {
            if ($child->type === Item::TYPE_PERMISSION) {
                $result[] = $child->name;
            }
        }
        return $result;
    }

    /** Унаследованные роли. @return string[] */
    public function getChildRoles(string $roleName): array
    {
        $result = [];
        foreach ($this->auth()->getChildren($roleName) as $child) {
            if ($child->type === Item::TYPE_ROLE) {
                $result[] = $child->name;
            }
        }
        return $result;
    }

    /** Все разрешения роли с учётом наследования. @return string[] */
    public function getEffectiveRolePermissions(string $roleName): array
    {
        return array_keys($this->auth()->getPermissionsByRole($roleName));
    }

    /** @return array<string, int> [role => число пользователей] */
    public function getRoleUserCounts(): array
    {
        $rows = (new \yii\db\Query())
            ->select(['item_name', 'cnt' => 'COUNT(*)'])
            ->from($this->auth()->assignmentTable)
            ->groupBy('item_name')
            ->all($this->auth()->db);
        return array_column($rows, 'cnt', 'item_name');
    }

    // ---------------------------------------------------------------- Пользователь

    /** @return string[] роли пользователя */
    public function getUserRoles(int $userId): array
    {
        return array_keys($this->auth()->getRolesByUser($userId));
    }

    /** @return string[] разрешения, назначенные пользователю напрямую (не через роли) */
    public function getUserDirectPermissions(int $userId): array
    {
        $result = [];
        foreach ($this->auth()->getAssignments($userId) as $name => $assignment) {
            $item = $this->auth()->getPermission($name);
            if ($item !== null) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /** @return string[] итоговые разрешения пользователя */
    public function getUserEffectivePermissions(int $userId): array
    {
        return array_keys($this->auth()->getPermissionsByUser($userId));
    }

    public function countUsersWithPermission(string $permission, ?int $exceptUserId = null): int
    {
        $count = 0;
        $userIds = (new \yii\db\Query())->select('user_id')->distinct()
            ->from($this->auth()->assignmentTable)->column($this->auth()->db);
        foreach ($userIds as $id) {
            if ((int)$id === $exceptUserId) {
                continue;
            }
            $user = User::findOne((int)$id);
            if ($user && $user->isActive() && $this->auth()->checkAccess($id, $permission)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Полная замена ролей и прямых разрешений пользователя.
     *
     * @param string[] $roles
     * @param string[] $permissions
     */
    public function setUserAccess(User $user, array $roles, array $permissions, User $actor): void
    {
        $auth = $this->auth();
        $roles = array_values(array_intersect($roles, array_keys($auth->getRoles())));
        $permissions = array_values(array_intersect($permissions, array_keys($auth->getPermissions())));

        $tx = $auth->db->beginTransaction();
        try {
            $auth->revokeAll($user->id);
            foreach ($roles as $name) {
                $auth->assign($auth->getRole($name), $user->id);
            }
            foreach ($permissions as $name) {
                $auth->assign($auth->getPermission($name), $user->id);
            }
            $this->guardLockout($actor);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        } finally {
            $auth->invalidateCache();
        }
        Yii::info("Access of user #{$user->id} set by #{$actor->id}: roles=" . implode(',', $roles) . '; perms=' . implode(',', $permissions), 'access');
    }

    // ---------------------------------------------------------------- Роли

    /**
     * Создание или изменение роли.
     *
     * @param string[] $permissions прямые разрешения
     * @param string[] $inherits роли, права которых наследуются
     */
    public function saveRole(?string $oldName, string $name, string $description, array $permissions, array $inherits, User $actor): Role
    {
        $auth = $this->auth();
        $isNew = $oldName === null;

        if (!$isNew && $this->isSystemRole($oldName) && $oldName !== $name) {
            throw new DomainException('Системную роль нельзя переименовать.');
        }
        if ($this->isSystemRole($name) && $isNew) {
            throw new DomainException('Роль с таким именем уже существует.');
        }
        if (($isNew || $oldName !== $name) && ($auth->getRole($name) !== null || $auth->getPermission($name) !== null)) {
            throw new DomainException("Роль или разрешение «{$name}» уже существует.");
        }
        if (in_array($name, $inherits, true) || ($oldName && in_array($oldName, $inherits, true))) {
            throw new DomainException('Роль не может наследовать сама себя.');
        }

        $tx = $auth->db->beginTransaction();
        try {
            if ($isNew) {
                $role = $auth->createRole($name);
                $role->description = $description;
                $auth->add($role);
            } else {
                $role = $auth->getRole($oldName) ?? throw new DomainException('Роль не найдена.');
                $role->name = $name;
                $role->description = $description;
                $auth->update($oldName, $role); // назначения и связи переносятся DbManager
            }

            // Пересобираем дочерние элементы
            foreach ($auth->getChildren($name) as $child) {
                $auth->removeChild($role, $child);
            }
            foreach (array_unique($inherits) as $childName) {
                $child = $auth->getRole($childName);
                if ($child === null) {
                    continue;
                }
                if (!$auth->canAddChild($role, $child)) {
                    throw new DomainException("Наследование от «{$this->roleLabel($childName)}» создаёт цикл.");
                }
                $auth->addChild($role, $child);
            }
            foreach (array_unique($permissions) as $permName) {
                $perm = $auth->getPermission($permName);
                if ($perm !== null) {
                    $auth->addChild($role, $perm);
                }
            }

            if ($name === Rbac::ROLE_ADMIN && !in_array(self::LOCKOUT_PERMISSION, $this->getEffectiveRolePermissions($name), true)) {
                throw new DomainException('У роли «Администратор» нельзя отнять управление пользователями и ролями.');
            }
            $this->guardLockout($actor);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            $auth->invalidateCache();
            throw $e instanceof DomainException ? $e : new DomainException('Не удалось сохранить роль: ' . $e->getMessage(), 0, $e);
        }
        $auth->invalidateCache();
        Yii::info("Role {$name} saved by #{$actor->id}", 'access');
        return $auth->getRole($name);
    }

    /**
     * Сохранение матрицы «роль × разрешение» (только прямые разрешения ролей).
     *
     * @param array<string, string[]> $matrix [role => [permission, ...]]
     */
    public function saveMatrix(array $matrix, User $actor): void
    {
        $auth = $this->auth();
        $tx = $auth->db->beginTransaction();
        try {
            foreach ($auth->getRoles() as $roleName => $role) {
                $wanted = array_flip((array)($matrix[$roleName] ?? []));
                foreach ($this->getPermissions() as $permName => $perm) {
                    $has = $auth->hasChild($role, $perm);
                    if (isset($wanted[$permName]) && !$has) {
                        $auth->addChild($role, $perm);
                    } elseif (!isset($wanted[$permName]) && $has) {
                        $auth->removeChild($role, $perm);
                    }
                }
            }
            $auth->invalidateCache();
            if (!in_array(self::LOCKOUT_PERMISSION, $this->getEffectiveRolePermissions(Rbac::ROLE_ADMIN), true)) {
                throw new DomainException('У роли «Администратор» нельзя отнять управление пользователями и ролями.');
            }
            $this->guardLockout($actor);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        } finally {
            $auth->invalidateCache();
        }
        Yii::info("Permission matrix saved by #{$actor->id}", 'access');
    }

    public function deleteRole(string $name, User $actor): void
    {
        if ($this->isSystemRole($name)) {
            throw new DomainException('Системную роль удалить нельзя.');
        }
        $auth = $this->auth();
        $role = $auth->getRole($name) ?? throw new DomainException('Роль не найдена.');
        $tx = $auth->db->beginTransaction();
        try {
            $auth->remove($role);
            $this->guardLockout($actor);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        } finally {
            $auth->invalidateCache();
        }
        Yii::info("Role {$name} deleted by #{$actor->id}", 'access');
    }

    /**
     * Нельзя лишить доступа к управлению себя и оставить систему без администраторов.
     */
    private function guardLockout(User $actor): void
    {
        $auth = $this->auth();
        $auth->invalidateCache();
        if (!$auth->checkAccess($actor->id, self::LOCKOUT_PERMISSION)) {
            throw new DomainException('Это изменение лишит вас доступа к управлению пользователями и ролями.');
        }
        if ($this->countUsersWithPermission(self::LOCKOUT_PERMISSION) < 1) {
            throw new DomainException('В системе должен остаться хотя бы один администратор.');
        }
    }
}
