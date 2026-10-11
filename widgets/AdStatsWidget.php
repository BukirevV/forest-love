<?php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * Блок агрегированных показателей кампании.
 */
class AdStatsWidget extends Widget
{
    /** Результат AdStatsService::getStats() */
    public array $stats = [];
    public bool $showSpecies = true;

    public function run(): string
    {
        $s = $this->stats;
        $f = Yii::$app->formatter;

        $tiles = [
            ['bi-tree-fill', $f->asInteger($s['trees_count'] ?? 0), $this->plural((int)($s['trees_count'] ?? 0), 'дерево посажено', 'дерева посажено', 'деревьев посажено')],
            ['bi-flower1', $f->asInteger(count($s['species'] ?? [])), $this->plural(count($s['species'] ?? []), 'порода', 'породы', 'пород')],
            ['bi-geo-alt-fill', $f->asInteger($s['regions_count'] ?? 0), 'зон посадок'],
        ];
        if (!empty($s['first_date'])) {
            $period = $f->asDate($s['first_date'], 'MM.yyyy');
            if (!empty($s['last_date']) && $s['last_date'] !== $s['first_date']) {
                $period .= ' — ' . $f->asDate($s['last_date'], 'MM.yyyy');
            }
            $tiles[] = ['bi-calendar-range', $period, 'период посадок'];
        }

        $html = '';
        foreach ($tiles as [$icon, $value, $label]) {
            $html .= Html::tag('div',
                Html::tag('div',
                    Html::tag('i', '', ['class' => "bi {$icon} fs-3 text-success"])
                    . Html::tag('div', Html::encode($value), ['class' => 'fs-3 fw-bold'])
                    . Html::tag('div', Html::encode($label), ['class' => 'small text-muted']),
                    ['class' => 'stat-tile p-3 rounded bg-light h-100 text-center']
                ),
                ['class' => 'col-6 col-md-3']
            );
        }
        $out = Html::tag('div', $html, ['class' => 'row g-3 ad-stats']);

        if (!empty($s['target'])) {
            $progress = (float)$s['progress'];
            $out .= Html::tag('div',
                Html::tag('div', "Цель кампании: {$f->asInteger($s['target'])} деревьев — выполнено {$progress}%", ['class' => 'small mb-1'])
                . Html::tag('div', Html::tag('div', '', [
                    'class' => 'progress-bar bg-success', 'style' => "width: {$progress}%",
                    'role' => 'progressbar', 'aria-valuenow' => $progress, 'aria-valuemin' => 0, 'aria-valuemax' => 100,
                ]), ['class' => 'progress']),
                ['class' => 'mt-3']
            );
        }

        if ($this->showSpecies && !empty($s['species'])) {
            $rows = '';
            $total = max(1, (int)$s['trees_count']);
            foreach ($s['species'] as $sp) {
                $pct = round($sp['count'] / $total * 100);
                $rows .= Html::tag('li',
                    Html::tag('span', Html::encode($sp['name'])) . Html::tag('span', "{$sp['count']} ({$pct}%)", ['class' => 'text-muted']),
                    ['class' => 'd-flex justify-content-between border-bottom py-1']
                );
            }
            $out .= Html::tag('h3', 'По породам', ['class' => 'h6 mt-4']) . Html::tag('ul', $rows, ['class' => 'list-unstyled mb-0']);
        }
        return $out;
    }

    private function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 > 1 && $n1 < 5) {
            return $few;
        }
        return $n1 === 1 ? $one : $many;
    }
}
