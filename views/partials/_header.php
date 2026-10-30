<?php

use app\rbac\Rbac;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var bool $overlay шапка поверх карты */

$overlay ??= false;
$user = Yii::$app->user;

NavBar::begin([
    'brandLabel' => '<i class="bi bi-tree-fill"></i> ' . Html::encode(Yii::$app->name),
    'brandUrl' => Yii::$app->homeUrl,
    'options' => ['class' => 'navbar-expand-md navbar-dark ' . ($overlay ? 'site-header--overlay' : 'bg-forest')],
]);

$items = [
    ['label' => 'Карта', 'url' => ['/site/index']],
    ['label' => 'О проекте', 'url' => ['/site/about']],
];
if ($user->can(Rbac::MODERATE)) {
    $items[] = ['label' => 'Админка', 'url' => ['/admin/default/index']];
}
echo Nav::widget(['options' => ['class' => 'navbar-nav me-auto'], 'items' => $items]);

// Поиск по имени дерева / подписи: подсказки при наборе (web/js/search.js), Enter — страница результатов
$searchQuery = Yii::$app->controller->id === 'search' ? (string)Yii::$app->request->get('q', '') : '';
echo Html::beginForm(['/search/index'], 'get', [
    'class' => 'site-search me-md-2 my-2 my-md-0',
    'role' => 'search',
    'data-site-search' => true,
    'data-suggest-url' => Url::to(['/search/suggest']),
    'data-home-url' => Url::to(['/site/index']),
]);
echo '<div class="input-group input-group-sm">'
    . '<span class="input-group-text"><i class="bi bi-search"></i></span>'
    . Html::input('search', 'q', $searchQuery, [
        'class' => 'form-control',
        'placeholder' => 'Найти дерево по имени',
        'aria-label' => 'Найти дерево по имени или подписи',
        'autocomplete' => 'off',
        'role' => 'combobox',
        'aria-autocomplete' => 'list',
        'aria-expanded' => 'false',
        'aria-controls' => 'site-search-list',
        'maxlength' => 100,
    ])
    . '</div>'
    . '<ul class="site-search__list dropdown-menu" id="site-search-list" role="listbox"></ul>';
echo Html::endForm();

echo Html::a('<i class="bi bi-plus-circle"></i> Посадить дерево', ['/planting/create'], ['class' => 'btn btn-warning me-md-2 my-2 my-md-0']);

if ($user->isGuest) {
    echo Nav::widget(['options' => ['class' => 'navbar-nav'], 'items' => [
        ['label' => 'Войти', 'url' => ['/auth/login']],
        ['label' => 'Регистрация', 'url' => ['/auth/signup']],
    ]]);
} else {
    echo Nav::widget(['options' => ['class' => 'navbar-nav'], 'items' => [
        ['label' => '<i class="bi bi-person-circle"></i> Кабинет', 'url' => ['/cabinet/index'], 'encode' => false],
    ]]);
    echo Html::beginForm(['/auth/logout'], 'post', ['class' => 'd-flex'])
        . Html::submitButton('Выйти', ['class' => 'btn btn-link nav-link text-white-50'])
        . Html::endForm();
}
NavBar::end();
