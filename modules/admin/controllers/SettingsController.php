<?php

namespace app\modules\admin\controllers;

use app\models\Setting;
use app\rbac\Rbac;
use Yii;
use yii\base\Model;
use yii\web\Response;

/**
 * Настройки: цена и срок именного размещения, лимиты загрузки и т.п.
 */
class SettingsController extends BaseAdminController
{
    protected string $permission = Rbac::MANAGE_SETTINGS;

    public function actionIndex(): Response|string
    {
        $settings = Setting::find()->orderBy(['key' => SORT_ASC])->indexBy('id')->all();

        if (Model::loadMultiple($settings, Yii::$app->request->post()) && Model::validateMultiple($settings, ['value'])) {
            foreach ($settings as $setting) {
                $setting->save(false, ['value', 'updated_at']);
            }
            Yii::$app->session->setFlash('success', 'Настройки сохранены.');
            return $this->refresh();
        }
        return $this->render('index', ['settings' => $settings]);
    }
}
