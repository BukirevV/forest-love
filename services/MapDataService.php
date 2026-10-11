<?php

namespace app\services;

use app\enums\NamedStatus;
use app\models\AdPlacement;
use app\models\Planting;
use Yii;
use yii\caching\TagDependency;
use yii\web\BadRequestHttpException;

/**
 * Данные для карты: GeoJSON опубликованных посадок по bbox, рекламные точки и баннеры (ТЗ 9.2).
 */
class MapDataService
{
    public const TAG_POINTS = 'map-points';
    public const TAG_ADS = 'map-ads';

    /**
     * Разбор параметра bbox "south,west,north,east".
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function parseBbox(?string $bbox): array
    {
        $parts = array_map('trim', explode(',', (string)$bbox));
        if (count($parts) !== 4 || array_filter($parts, static fn($v) => !is_numeric($v))) {
            throw new BadRequestHttpException('Параметр bbox должен иметь вид south,west,north,east.');
        }
        [$s, $w, $n, $e] = array_map('floatval', $parts);
        $s = max(-90.0, min(90.0, $s));
        $n = max(-90.0, min(90.0, $n));
        // Нормализация долгот (Leaflet может отдавать значения за пределами ±180)
        if ($e - $w >= 360) {
            $w = -180.0;
            $e = 180.0;
        } else {
            $w = fmod($w + 540, 360) - 180;
            $e = fmod($e + 540, 360) - 180;
        }
        return [$s, $w, $n, $e];
    }

    /**
     * GeoJSON FeatureCollection опубликованных посадок.
     */
    public function points(array $bbox, int $zoom = 0, ?int $speciesId = null, bool $namedOnly = false): array
    {
        $params = Yii::$app->params;
        $limit = (int)($params['map.maxPointsPerRequest'] ?? 5000);
        [$s, $w, $n, $e] = $bbox;

        // Округление bbox повышает попадание в кэш
        $key = [__METHOD__, round($s, 2), round($w, 2), round($n, 2), round($e, 2), $speciesId, $namedOnly];

        return Yii::$app->cache->getOrSet($key, function () use ($s, $w, $n, $e, $speciesId, $namedOnly, $limit) {
            $t = Planting::tableName();
            $query = Planting::find()
                ->select(["$t.id", "$t.latitude", "$t.longitude", "$t.named_status", "$t.named_until", "$t.species_id"])
                ->published()
                ->inBounds($s, $w, $n, $e)
                ->orderBy(["$t.named_status" => SORT_ASC, "$t.id" => SORT_DESC])
                ->limit($limit + 1)
                ->asArray();
            if ($speciesId) {
                $query->bySpecies($speciesId);
            }
            if ($namedOnly) {
                $query->named();
            }
            $rows = $query->all();
            $truncated = count($rows) > $limit;
            if ($truncated) {
                array_pop($rows);
            }

            $now = time();
            $features = [];
            foreach ($rows as $row) {
                $named = $row['named_status'] === NamedStatus::Active->value
                    && ($row['named_until'] === null || (int)$row['named_until'] > $now);
                $features[] = [
                    'type' => 'Feature',
                    'id' => (int)$row['id'],
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['longitude'], (float)$row['latitude']]],
                    'properties' => ['id' => (int)$row['id'], 'named' => $named, 'species_id' => (int)$row['species_id']],
                ];
            }
            return [
                'type' => 'FeatureCollection',
                'features' => $features,
                'meta' => ['count' => count($features), 'truncated' => $truncated],
            ];
        }, (int)($params['map.cacheTtl'] ?? 60), new TagDependency(['tags' => self::TAG_POINTS]));
    }

    /**
     * Данные карточки для всплывающего окна / боковой панели.
     */
    public function tree(int $id): ?array
    {
        return Yii::$app->cache->getOrSet([__METHOD__, $id], static function () use ($id) {
            $planting = Planting::find()
                ->published()
                ->andWhere([Planting::tableName() . '.id' => $id])
                ->with(['species', 'links', 'approvedPhotos', 'mainPhoto', 'adCampaign'])
                ->one();
            return $planting?->toPublicArray();
        }, 300, new TagDependency(['tags' => [self::TAG_POINTS, 'tree-' . $id]]));
    }

    /**
     * Активные рекламные точки в bbox (GeoJSON).
     */
    public function adPoints(array $bbox): array
    {
        [$s, $w, $n, $e] = $bbox;
        $key = [__METHOD__, round($s, 2), round($w, 2), round($n, 2), round($e, 2)];
        return Yii::$app->cache->getOrSet($key, static function () use ($s, $w, $n, $e) {
            $features = [];
            foreach (AdPlacement::find()->active()->points()->inBounds($s, $w, $n, $e)->byPriority()->with('campaign.advertiser')->limit(500)->all() as $p) {
                $features[] = [
                    'type' => 'Feature',
                    'id' => 'ad-' . $p->id,
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$p->longitude, (float)$p->latitude]],
                    'properties' => [
                        'id' => (int)$p->id,
                        'ad' => true,
                        'title' => $p->campaign->title,
                        'advertiser' => $p->campaign->advertiser->name,
                        'icon' => $p->getMarkerIconUrl(),
                        'url' => $p->getTargetUrl(),
                        'external' => (bool)$p->target_url,
                    ],
                ];
            }
            return ['type' => 'FeatureCollection', 'features' => $features];
        }, 120, new TagDependency(['tags' => self::TAG_ADS]));
    }

    /**
     * Активные баннеры, по одному (с наибольшим приоритетом) на позицию.
     */
    public function banners(): array
    {
        return Yii::$app->cache->getOrSet(__METHOD__, static function () {
            $result = [];
            foreach (AdPlacement::find()->active()->banners()->byPriority()->with('campaign')->all() as $p) {
                if (isset($result[$p->position])) {
                    continue;
                }
                $result[$p->position] = [
                    'id' => (int)$p->id,
                    'position' => $p->position,
                    'image' => $p->getImageUrl(),
                    'imageMobile' => $p->getImageMobileUrl(),
                    'url' => $p->getTargetUrl(),
                    'external' => (bool)$p->target_url,
                    'title' => $p->campaign->title,
                    'legal' => $p->campaign->legal_text,
                ];
            }
            return array_values($result);
        }, 120, new TagDependency(['tags' => self::TAG_ADS]));
    }

    public function invalidate(): void
    {
        TagDependency::invalidate(Yii::$app->cache, self::TAG_POINTS);
    }

    public function invalidateTree(int $id): void
    {
        TagDependency::invalidate(Yii::$app->cache, 'tree-' . $id);
    }

    public function invalidateAds(): void
    {
        TagDependency::invalidate(Yii::$app->cache, self::TAG_ADS);
    }
}
