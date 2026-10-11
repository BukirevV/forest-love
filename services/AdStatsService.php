<?php

namespace app\services;

use app\enums\PlantingStatus;
use app\models\AdCampaign;
use app\models\Planting;
use app\models\TreeSpecies;
use Yii;
use yii\caching\TagDependency;
use yii\db\Expression;
use yii\db\Query;

/**
 * Агрегированные показатели кампании; кэш с инвалидацией при публикации/скрытии связанной посадки.
 */
class AdStatsService
{
    private static function tag(int $campaignId): string
    {
        return 'ad-stats-' . $campaignId;
    }

    /**
     * @return array{trees_count: int, species: array, first_date: ?string, last_date: ?string, regions_count: int, target: ?int, progress: ?float}
     */
    public function getStats(AdCampaign $campaign): array
    {
        $id = (int)$campaign->id;
        return Yii::$app->cache->getOrSet([__METHOD__, $id], static function () use ($id, $campaign) {
            $p = Planting::tableName();
            $s = TreeSpecies::tableName();
            $base = (new Query())->from($p)->where(["$p.ad_campaign_id" => $id, "$p.status" => PlantingStatus::Approved->value]);

            $agg = (clone $base)->select([
                'cnt' => new Expression('COUNT(*)'),
                'first_date' => new Expression("MIN($p.planted_at)"),
                'last_date' => new Expression("MAX($p.planted_at)"),
                // Охват: количество «ячеек» 1°×1° (до согласования методики по регионам, ТЗ 22 вопрос 8)
                'regions' => new Expression("COUNT(DISTINCT CONCAT(FLOOR($p.latitude), ':', FLOOR($p.longitude)))"),
            ])->one();

            $species = (clone $base)
                ->select(['species_id' => "$s.id", 'name' => "$s.name", 'count' => new Expression('COUNT(*)')])
                ->innerJoin($s, "$s.id = $p.species_id")
                ->groupBy(["$s.id", "$s.name"])
                ->orderBy(['count' => SORT_DESC])
                ->all();

            $count = (int)($agg['cnt'] ?? 0);
            $target = $campaign->funded_trees_target ? (int)$campaign->funded_trees_target : null;

            return [
                'trees_count' => $count,
                'species' => array_map(static fn($r) => [
                    'species_id' => (int)$r['species_id'],
                    'name' => $r['name'],
                    'count' => (int)$r['count'],
                ], $species),
                'first_date' => $agg['first_date'] ?? null,
                'last_date' => $agg['last_date'] ?? null,
                'regions_count' => (int)($agg['regions'] ?? 0),
                'target' => $target,
                'progress' => $target ? min(100, round($count / $target * 100, 1)) : null,
            ];
        }, 3600, new TagDependency(['tags' => self::tag($id)]));
    }

    public function invalidate(int $campaignId): void
    {
        TagDependency::invalidate(Yii::$app->cache, self::tag($campaignId));
    }

    public function invalidateForPlanting(Planting $planting): void
    {
        if ($planting->ad_campaign_id) {
            $this->invalidate((int)$planting->ad_campaign_id);
        }
    }
}
