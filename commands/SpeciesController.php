<?php

namespace app\commands;

use app\models\TreeSpecies;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Справочник пород.
 */
class SpeciesController extends Controller
{
    /** Разделитель CSV */
    public string $delimiter = ';';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['delimiter']);
    }

    /**
     * Импорт: yii species/import data/species.csv
     * Формат: name;latin_name;sort;description (первая строка — заголовок). Существующие породы обновляются по name.
     */
    public function actionImport(string $file): int
    {
        if (!is_readable($file)) {
            $this->stderr("Файл {$file} не найден.\n");
            return ExitCode::NOINPUT;
        }
        $handle = fopen($file, 'rb');
        $header = fgetcsv($handle, 0, $this->delimiter);
        if ($header && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // BOM
        }
        $created = $updated = $errors = 0;
        $line = 1;
        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $line++;
            if (!array_filter($row)) {
                continue;
            }
            $data = array_combine(
                array_slice($header, 0, count($row)),
                array_slice(array_pad($row, count($header), null), 0, count($row))
            );
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $model = TreeSpecies::findOne(['name' => $name]) ?? new TreeSpecies(['name' => $name]);
            $isNew = $model->isNewRecord;
            $model->latin_name = trim((string)($data['latin_name'] ?? '')) ?: $model->latin_name;
            $model->description = trim((string)($data['description'] ?? '')) ?: $model->description;
            $model->sort = (int)($data['sort'] ?? $model->sort ?? 0);
            $model->is_active ??= true;
            if ($model->save()) {
                $isNew ? $created++ : $updated++;
            } else {
                $errors++;
                $this->stderr("Строка {$line}: " . implode('; ', $model->getFirstErrors()) . "\n");
            }
        }
        fclose($handle);
        $this->stdout("Создано: {$created}, обновлено: {$updated}, ошибок: {$errors}\n");
        return $errors ? ExitCode::DATAERR : ExitCode::OK;
    }
}
