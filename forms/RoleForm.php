<?php

namespace app\forms;

use app\rbac\Rbac;
use Yii;
use yii\base\Model;
use yii\rbac\Role;

/**
 * Создание и редактирование роли.
 */
class RoleForm extends Model
{
    public ?string $name = null;
    public ?string $description = null;
    /** @var string[]|string */
    public array|string|null $permissions = [];
    /** @var string[]|string */
    public array|string|null $inherits = [];

    public ?string $oldName = null;

    public static function fromRole(Role $role, array $permissions, array $inherits): self
    {
        return new self([
            'name' => $role->name,
            'oldName' => $role->name,
            'description' => $role->description,
            'permissions' => $permissions,
            'inherits' => $inherits,
        ]);
    }

    public function rules(): array
    {
        return [
            [['name', 'description'], 'trim'],
            [['name', 'description'], 'required'],
            ['name', 'string', 'max' => 64],
            ['name', 'match', 'pattern' => '/^[a-z][a-zA-Z0-9_-]*$/',
                'message' => 'Код роли: латинские буквы, цифры, «_» и «-», начинается с буквы в нижнем регистре.'],
            ['name', 'validateSystemName'],
            ['description', 'string', 'max' => 200],
            [['permissions', 'inherits'], 'default', 'value' => []],
            [['permissions', 'inherits'], 'each', 'rule' => ['string']],
        ];
    }

    public function validateSystemName(string $attribute): void
    {
        if ($this->oldName !== null && isset(Rbac::ROLES[$this->oldName]) && $this->name !== $this->oldName) {
            $this->addError($attribute, 'Системную роль нельзя переименовать.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Код роли',
            'description' => 'Название',
            'permissions' => 'Разрешения',
            'inherits' => 'Включает права ролей',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'name' => 'Например: editor, ad_manager. Используется в коде и не показывается пользователям.',
            'inherits' => 'Роль получает все разрешения выбранных ролей.',
        ];
    }

    public function isNew(): bool
    {
        return $this->oldName === null;
    }

    public function isSystem(): bool
    {
        return $this->oldName !== null && isset(Rbac::ROLES[$this->oldName]);
    }
}
