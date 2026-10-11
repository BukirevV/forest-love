<?php

namespace app\commands;

use app\components\storage\StorageInterface;
use app\models\AdPlacement;
use app\models\Advertiser;
use app\models\PlantingPhoto;
use app\models\VerificationCode;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Служебная очистка (cron).
 */
class CleanupController extends Controller
{
    /** Не удалять файлы моложе N секунд (могут быть в процессе загрузки/обработки). */
    public int $minAge = 86400;
    /** Только показать, что будет удалено. */
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['minAge', 'dryRun']);
    }

    /**
     * Удаление просроченных и использованных кодов подтверждения (ежедневно).
     */
    public function actionCodes(): int
    {
        $count = VerificationCode::deleteAll(['or',
            ['<', 'expires_at', time() - 86400],
            ['and', ['not', ['used_at' => null]], ['<', 'used_at', time() - 86400]],
        ]);
        $this->stdout("Удалено кодов: {$count}\n");
        return ExitCode::OK;
    }

    /**
     * Удаление файлов без записей в БД.
     */
    public function actionOrphanFiles(): int
    {
        /** @var StorageInterface $storage */
        $storage = Yii::$app->get('storage');

        $known = ['private' => [], 'public' => []];
        foreach (PlantingPhoto::find()->select(['file_path', 'public_path', 'thumb_path'])->asArray()->each(1000) as $row) {
            $known['private'][$row['file_path']] = true;
            foreach (['public_path', 'thumb_path'] as $k) {
                if ($row[$k]) {
                    $known['public'][$row[$k]] = true;
                }
            }
        }
        foreach (Advertiser::find()->select('logo_path')->where(['not', ['logo_path' => null]])->column() as $path) {
            $known['public'][$path] = true;
        }
        foreach (AdPlacement::find()->select(['image_path', 'image_mobile_path', 'marker_icon'])->asArray()->each(500) as $row) {
            foreach ($row as $path) {
                if ($path) {
                    $known['public'][$path] = true;
                }
            }
        }

        $removed = 0;
        foreach ([true, false] as $private) {
            $zone = $private ? 'private' : 'public';
            foreach ($storage->listFiles($private) as $path) {
                if (isset($known[$zone][$path])) {
                    continue;
                }
                $mtime = $storage->getModifiedTime($private, $path);
                if ($mtime !== null && $mtime > time() - $this->minAge) {
                    continue;
                }
                $this->stdout(($this->dryRun ? '[dry-run] ' : '') . "{$zone}: {$path}\n");
                if (!$this->dryRun) {
                    $private ? $storage->deletePrivate($path) : $storage->deletePublic($path);
                }
                $removed++;
            }
        }
        $this->stdout("Файлов-сирот: {$removed}\n");
        return ExitCode::OK;
    }
}
