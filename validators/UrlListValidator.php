<?php

namespace app\validators;

use Yii;
use yii\validators\UrlValidator;
use yii\validators\Validator;

/**
 * Список ссылок «Кто посадил»: массив элементов [url, label], лимит количества,
 * допустимые схемы только http/https. Пустые строки отбрасываются.
 */
class UrlListValidator extends Validator
{
    public ?int $max = null;
    public int $maxUrlLength = 500;
    public int $maxLabelLength = 100;
    public array $validSchemes = ['http', 'https'];

    public function init(): void
    {
        parent::init();
        $this->max ??= (int)(Yii::$app->params['links.maxCount'] ?? 50);
    }

    public function validateAttribute($model, $attribute): void
    {
        $items = $model->$attribute;
        if ($items === null || $items === '') {
            $model->$attribute = [];
            return;
        }
        if (!is_array($items)) {
            $this->addError($model, $attribute, 'Некорректный формат списка ссылок.');
            return;
        }

        $normalized = [];
        $urlValidator = new UrlValidator([
            'defaultScheme' => 'https',
            'validSchemes' => $this->validSchemes,
            'enableIDN' => function_exists('idn_to_ascii'),
        ]);

        foreach (array_values($items) as $i => $item) {
            if (is_string($item)) {
                $item = ['url' => $item];
            }
            $url = trim((string)($item['url'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            if ($url === '') {
                continue;
            }
            $num = $i + 1;

            if (self::hasForbiddenScheme($url)) {
                $this->addError($model, $attribute, "Ссылка №{$num}: недопустимая схема.");
                continue;
            }
            if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            if (mb_strlen($url) > $this->maxUrlLength) {
                $this->addError($model, $attribute, "Ссылка №{$num} слишком длинная.");
                continue;
            }
            if (!$urlValidator->validate($url)) {
                $this->addError($model, $attribute, "Ссылка №{$num} некорректна: {$url}");
                continue;
            }
            if (mb_strlen($label) > $this->maxLabelLength) {
                $this->addError($model, $attribute, "Подпись к ссылке №{$num} длиннее {$this->maxLabelLength} символов.");
                continue;
            }
            $normalized[] = ['url' => $url, 'label' => $label !== '' ? $label : null];
        }

        if (count($normalized) > $this->max) {
            $this->addError($model, $attribute, "Можно указать не более {$this->max} ссылок.");
        }

        if (!$model->hasErrors($attribute)) {
            $model->$attribute = $normalized;
        }
    }

    public static function hasForbiddenScheme(string $url): bool
    {
        $clean = strtolower(preg_replace('/[\x00-\x20]+/', '', $url));
        return (bool)preg_match('~^(javascript|data|vbscript|file|about|blob):~', $clean);
    }
}
