<?php

namespace app\services;

use app\enums\NamedStatus;
use app\models\Planting;
use app\models\query\PlantingQuery;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Поиск опубликованных посадок по имени дерева / подписи (поле title).
 * Без учёта регистра и разницы «ё/е».
 */
class SearchService
{
    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 100;

    /**
     * Нормализованная строка запроса или null, если искать нечего.
     */
    public function normalize(?string $q): ?string
    {
        $q = trim(preg_replace('/\s+/u', ' ', (string)$q) ?? '');
        $q = mb_substr($q, 0, self::MAX_LENGTH);
        return mb_strlen($q) >= self::MIN_LENGTH ? $q : null;
    }

    /**
     * Подсказки для поля поиска.
     *
     * @return array<int, array{id: int, title: string, species: ?string, named: bool, latitude: float, longitude: float, url: string}>
     */
    public function suggest(string $q, int $limit = 8): array
    {
        $items = [];
        foreach ($this->query($q)->with('species')->limit($limit)->all() as $p) {
            $items[] = [
                'id' => (int)$p->id,
                'title' => (string)$p->title,
                'species' => $p->species?->name,
                'named' => $p->isNamed(),
                'latitude' => (float)$p->latitude,
                'longitude' => (float)$p->longitude,
                'url' => $p->getPublicUrl(),
            ];
        }
        return $items;
    }

    /**
     * Результаты для страницы поиска.
     */
    public function search(string $q, int $pageSize = 24): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->query($q)->with(['species', 'mainPhoto', 'approvedPhotos']),
            'pagination' => ['pageSize' => $pageSize, 'pageSizeParam' => false],
            'sort' => false,
        ]);
    }

    /**
     * Сначала совпадения с начала имени, затем именные деревья, затем по алфавиту.
     */
    private function query(string $q): PlantingQuery
    {
        $t = Planting::tableName();
        $title = "REPLACE(LOWER({$t}.title), 'ё', 'е')";
        $needle = str_replace('ё', 'е', mb_strtolower($q));
        $like = addcslashes($needle, '%_\\');

        return Planting::find()
            ->published()
            ->andWhere(['not', ["{$t}.title" => null]])
            ->andWhere(new Expression("{$title} LIKE :contains", [':contains' => '%' . $like . '%']))
            ->orderBy([
                new Expression("CASE WHEN {$title} LIKE :prefix THEN 0 ELSE 1 END", [':prefix' => $like . '%']),
                new Expression("CASE WHEN {$t}.named_status = :named THEN 0 ELSE 1 END", [':named' => NamedStatus::Active->value]),
                "{$t}.title" => SORT_ASC,
                "{$t}.id" => SORT_DESC,
            ]);
    }
}
