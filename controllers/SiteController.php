<?php

namespace app\controllers;

use app\models\TreeSpecies;
use Yii;
use yii\web\Controller;
use yii\web\ErrorAction;

/**
 * Публичные страницы: карта, о проекте, правовые документы, ошибки.
 */
class SiteController extends Controller
{
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
                'layout' => 'main',
            ],
        ];
    }

    /**
     * Главная — полноэкранная интерактивная карта.
     */
    public function actionIndex(): string
    {
        $this->layout = 'map';
        return $this->render('index', [
            'speciesList' => TreeSpecies::getList(),
            'focusTreeId' => (int)Yii::$app->request->get('tree') ?: null,
        ]);
    }

    public function actionAbout(): string
    {
        return $this->render('about');
    }

    public function actionPrivacy(): string
    {
        return $this->render('privacy');
    }

    public function actionTerms(): string
    {
        return $this->render('terms');
    }
}
