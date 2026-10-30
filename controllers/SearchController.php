<?php

namespace app\controllers;

use app\services\SearchService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Поиск посадок по имени дерева / подписи.
 */
class SearchController extends Controller
{
    public function __construct($id, $module, private readonly SearchService $search, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * GET /search?q=… — страница результатов.
     */
    public function actionIndex(?string $q = null): string
    {
        $query = $this->search->normalize($q);
        return $this->render('index', [
            'q' => trim((string)$q),
            'dataProvider' => $query !== null ? $this->search->search($query) : null,
            'tooShort' => $query === null && trim((string)$q) !== '',
        ]);
    }

    /**
     * GET /api/search/suggest?q=… — подсказки (JSON).
     */
    public function actionSuggest(?string $q = null): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $query = $this->search->normalize($q);
        if ($query === null) {
            return ['items' => []];
        }
        $items = Yii::$app->cache->getOrSet(
            [__METHOD__, mb_strtolower($query)],
            fn() => $this->search->suggest($query),
            60
        );
        return ['items' => $items];
    }
}
