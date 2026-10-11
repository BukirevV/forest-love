<?php

namespace app\widgets;

use Yii;
use yii\helpers\Html;
use yii\widgets\InputWidget;

/**
 * Динамический ввод списка ссылок «Кто посадил»: добавить / удалить / переставить.
 * Имена полей: Model[attr][i][url], Model[attr][i][label].
 * JS-поведение — в web/js/planting-form.js (data-links-input).
 */
class LinksInputWidget extends InputWidget
{
    public ?int $max = null;

    public function run(): string
    {
        $this->max ??= (int)(Yii::$app->params['links.maxCount'] ?? 50);
        $name = Html::getInputName($this->model, $this->attribute);
        $values = Html::getAttributeValue($this->model, $this->attribute);
        $values = is_array($values) && $values ? array_values($values) : [['url' => '', 'label' => '']];

        $rows = [];
        foreach ($values as $i => $item) {
            $rows[] = $this->renderRow($name, (string)$i, (string)($item['url'] ?? ''), (string)($item['label'] ?? ''));
        }

        $template = Html::tag('template', $this->renderRow($name, '__INDEX__', '', ''), ['data-links-template' => true]);

        $addButton = Html::button(
            '<i class="bi bi-plus-lg"></i> Добавить ссылку',
            ['class' => 'btn btn-outline-success btn-sm', 'data-links-add' => true]
        );

        return Html::tag('div',
            Html::tag('div', implode("\n", $rows), ['data-links-rows' => true]) . $template . $addButton,
            ['data-links-input' => true, 'data-max' => $this->max, 'id' => $this->options['id'] ?? $this->getId()]
        );
    }

    private function renderRow(string $name, string $index, string $url, string $label): string
    {
        $urlInput = Html::input('url', "{$name}[{$index}][url]", $url, [
            'class' => 'form-control',
            'placeholder' => 'https://vk.com/… или https://t.me/…',
            'inputmode' => 'url',
            'maxlength' => 500,
        ]);
        $labelInput = Html::input('text', "{$name}[{$index}][label]", $label, [
            'class' => 'form-control',
            'placeholder' => 'Подпись (необязательно)',
            'maxlength' => 100,
        ]);
        $controls = Html::tag('div',
            Html::button('<i class="bi bi-arrow-up"></i>', ['class' => 'btn btn-outline-secondary', 'data-links-up' => true, 'title' => 'Выше'])
            . Html::button('<i class="bi bi-arrow-down"></i>', ['class' => 'btn btn-outline-secondary', 'data-links-down' => true, 'title' => 'Ниже'])
            . Html::button('<i class="bi bi-x-lg"></i>', ['class' => 'btn btn-outline-danger', 'data-links-remove' => true, 'title' => 'Удалить']),
            ['class' => 'btn-group btn-group-sm']
        );

        return Html::tag('div',
            Html::tag('div', $urlInput, ['class' => 'col-12 col-md-6'])
            . Html::tag('div', $labelInput, ['class' => 'col-8 col-md-4'])
            . Html::tag('div', $controls, ['class' => 'col-4 col-md-2 text-end']),
            ['class' => 'row g-2 mb-2 links-row', 'data-links-row' => true]
        );
    }
}
