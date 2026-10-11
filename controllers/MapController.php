<?php

namespace app\controllers;

use app\models\AdPlacement;
use app\services\MapDataService;
use Yii;
use yii\filters\Cors;
use yii\filters\HttpCache;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * JSON API для карты (ТЗ 10.3).
 */
class MapController extends Controller
{
    public $enableCsrfValidation = true;

    public function __construct($id, $module, private readonly MapDataService $mapData, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'points' => ['get'],
                    'tree' => ['get'],
                    'ad-points' => ['get'],
                    'banners' => ['get'],
                    'ad-click' => ['post'],
                    'ad-impression' => ['post'],
                ],
            ],
            'httpCache' => [
                'class' => HttpCache::class,
                'only' => ['points', 'tree', 'ad-points', 'banners'],
                'cacheControlHeader' => 'public, max-age=30',
                'etagSeed' => static function ($action) {
                    return $action->id . '|' . Yii::$app->request->queryString;
                },
                'sessionCacheLimiter' => '',
                'enabled' => false, // включить при наличии ETag на основе версии данных
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (in_array($action->id, ['points', 'tree', 'ad-points', 'banners'], true)) {
            Yii::$app->response->headers->set('Cache-Control', 'public, max-age=30');
        }
        return parent::beforeAction($action);
    }

    /**
     * GET api/map/points?bbox=s,w,n,e&zoom=&species_id=&named=1
     */
    public function actionPoints(string $bbox, int $zoom = 0, ?int $species_id = null, ?int $named = null): array
    {
        return $this->mapData->points($this->mapData->parseBbox($bbox), $zoom, $species_id ?: null, (bool)$named);
    }

    /**
     * GET api/map/tree/{id}
     */
    public function actionTree(int $id): array
    {
        $data = $this->mapData->tree($id);
        if ($data === null) {
            throw new NotFoundHttpException('Дерево не найдено.');
        }
        $data['popup'] = $this->renderPartial('/tree/_popup', ['tree' => $data]);
        return $data;
    }

    /**
     * GET api/map/ads?bbox=s,w,n,e
     */
    public function actionAdPoints(string $bbox): array
    {
        return $this->mapData->adPoints($this->mapData->parseBbox($bbox));
    }

    /**
     * GET api/map/banners
     */
    public function actionBanners(): array
    {
        return ['banners' => $this->mapData->banners()];
    }

    /**
     * POST api/map/ad-click/{id}
     */
    public function actionAdClick(int $id): array
    {
        $placement = AdPlacement::find()->active()->andWhere([AdPlacement::tableName() . '.id' => $id])->one();
        if (!$placement) {
            throw new NotFoundHttpException();
        }
        $placement->registerClick();
        return ['success' => true, 'url' => $placement->getTargetUrl()];
    }

    /**
     * POST api/map/ad-impression  ids[]=1&ids[]=2 — учёт показов (пакетно).
     */
    public function actionAdImpression(): array
    {
        $ids = array_slice(array_map('intval', (array)Yii::$app->request->post('ids', [])), 0, 50);
        if ($ids) {
            AdPlacement::updateAllCounters(['impressions' => 1], ['id' => $ids]);
        }
        return ['success' => true];
    }
}
