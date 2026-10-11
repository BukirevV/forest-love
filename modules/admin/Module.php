<?php

namespace app\modules\admin;

/**
 * Модуль администрирования и модерации (ТЗ 11). URL-префикс /admin.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\admin\controllers';
    public $layout = 'main';
    public $defaultRoute = 'default';
}
