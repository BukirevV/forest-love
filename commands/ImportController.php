<?php

namespace app\commands;

use app\models\TreeSpecies;
use app\models\User;
use app\rbac\Rbac;
use app\services\PhotoImportService;
use DomainException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * Импорт посадок из фотографий.
 *
 *   yii import                         — импортировать все фото из import/in
 *   yii import --species="Дуб черешчатый"  — порода для фото, у которых её нет в метаданных (название или ID)
 *   yii import --user=admin@site.ru    — от чьего имени создавать (email, телефон или ID; по умолчанию — первый администратор)
 *   yii import --dry-run               — только показать, что будет импортировано, ничего не создавая и не перемещая
 *
 * Из метаданных берутся GPS-координаты (обязательны), дата съёмки (дата посадки),
 * заголовок и описание (XMP / IPTC / EXIF), ключевые слова — для определения породы.
 * Успешно импортированные файлы переносятся в import/success, остальные — в import/fail.
 */
class ImportController extends Controller
{
    public $defaultAction = 'index';

    /**
     * @var string|null порода для фото, у которых её нет в метаданных: название (русское/латинское) или ID
     */
    public ?string $species = null;
    /**
     * @var string|null владелец карточек: email, телефон или ID (по умолчанию — первый администратор)
     */
    public ?string $user = null;
    /**
     * @var bool пробный прогон: показать результат, ничего не создавая и не перемещая
     */
    public bool $dryRun = false;

    public string $inPath = '@app/import/in';
    public string $successPath = '@app/import/success';
    public string $failPath = '@app/import/fail';

    public function __construct($id, $module, private readonly PhotoImportService $importer, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['species', 'user', 'dryRun']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['s' => 'species', 'u' => 'user', 'n' => 'dryRun']);
    }

    public function actionIndex(): int
    {
        $in = Yii::getAlias($this->inPath);
        $success = Yii::getAlias($this->successPath);
        $fail = Yii::getAlias($this->failPath);
        foreach ([$in, $success, $fail] as $dir) {
            FileHelper::createDirectory($dir, 0775);
        }

        try {
            $owner = $this->resolveOwner();
            $defaultSpecies = $this->resolveSpecies();
        } catch (DomainException $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $files = $this->findFiles($in);
        if (!$files) {
            $this->stdout("В папке {$in} нет файлов для импорта.\n");
            return ExitCode::OK;
        }

        $this->stdout(($this->dryRun ? 'ПРОБНЫЙ ПРОГОН, ничего не сохраняется. ' : '')
            . 'Файлов: ' . count($files) . '. Владелец: ' . $owner->getPrivateName() . " (ID {$owner->id})"
            . ($defaultSpecies ? ". Порода по умолчанию: {$defaultSpecies->name}" : '') . "\n\n");

        $rows = [];
        $ok = $failed = 0;
        foreach ($files as $i => $file) {
            $name = basename($file);
            $this->stdout(sprintf('[%d/%d] %s … ', $i + 1, count($files), $name));
            try {
                if ($this->dryRun) {
                    $details = $this->preview($file, $defaultSpecies);
                } else {
                    $planting = $this->importer->import($file, $owner, $defaultSpecies);
                    $details = "посадка #{$planting->id}, {$planting->species->name}, "
                        . "{$planting->latitude}, {$planting->longitude}";
                    try {
                        $moved = $this->moveTo($file, $success);
                        if ($moved !== $name) {
                            $details .= " (сохранён как {$moved})";
                        }
                    } catch (\Throwable $moveError) {
                        // Посадка уже создана — это успех, но файл остался в in и при повторном запуске создаст дубль.
                        $details .= ' | ВНИМАНИЕ: файл не перемещён в success, удалите его из in вручную ('
                            . $moveError->getMessage() . ')';
                    }
                }
                $ok++;
                $this->stdout("OK\n", Console::FG_GREEN);
                $rows[] = [$name, true, $details];
            } catch (\Throwable $e) {
                $failed++;
                $reason = $e instanceof DomainException ? $e->getMessage() : get_class($e) . ': ' . $e->getMessage();
                Yii::warning("Импорт {$name}: {$reason}", __METHOD__);
                if (!$this->dryRun) {
                    try {
                        $moved = $this->moveTo($file, $fail);
                        $this->writeReason($fail, $moved, $reason);
                    } catch (\Throwable $moveError) {
                        $reason .= ' | не удалось переместить в fail: ' . $moveError->getMessage();
                    }
                }
                $this->stdout("ОШИБКА\n", Console::FG_RED);
                $rows[] = [$name, false, $reason];
            }
        }

        if ($ok > 0 && !$this->dryRun) {
            $this->importer->finish();
        }

        $this->stdout("\nРезультат импорта:\n");
        foreach ($rows as [$name, $isOk, $details]) {
            $mark = $isOk ? '[OK]    ' : '[ОШИБКА]';
            $status = $isOk ? ($this->dryRun ? 'будет импортировано' : 'импортировано') : 'не импортировано';
            $this->stdout("  {$mark} ", $isOk ? Console::FG_GREEN : Console::FG_RED);
            $this->stdout("{$name} — {$status}: {$details}\n");
        }
        $this->stdout(sprintf("\nИтого файлов: %d, успешно: %d, с ошибкой: %d\n", count($files), $ok, $failed),
            $failed ? Console::FG_YELLOW : Console::FG_GREEN);
        if ($failed && !$this->dryRun) {
            $this->stdout("Файлы с ошибкой — в {$fail}, причина — в файле <имя>.error.txt рядом.\n");
        }
        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    // ------------------------------------------------------------------ helpers

    private function resolveOwner(): User
    {
        if ($this->user !== null && $this->user !== '') {
            $user = ctype_digit($this->user) ? User::findOne((int)$this->user) : User::findByLogin($this->user);
            if (!$user) {
                throw new DomainException("Пользователь «{$this->user}» не найден.");
            }
            return $user;
        }
        $ids = Yii::$app->authManager->getUserIdsByRole(Rbac::ROLE_ADMIN);
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $user = User::findOne((int)$id);
            if ($user && !$user->isBlocked()) {
                return $user;
            }
        }
        throw new DomainException('Нет ни одного администратора. Назначьте его (yii user/assign-role <id> admin) или укажите --user=…');
    }

    private function resolveSpecies(): ?TreeSpecies
    {
        if ($this->species === null || $this->species === '') {
            return null;
        }
        // ID, полное название или первое слово названия («ольха» → «Ольха чёрная», если такая порода одна)
        $species = ctype_digit($this->species)
            ? TreeSpecies::findOne((int)$this->species)
            : $this->importer->detectSpecies(['keywords' => [$this->species], 'title' => null, 'description' => null]);
        if (!$species) {
            $list = implode(', ', TreeSpecies::getList());
            throw new DomainException("Порода «{$this->species}» не найдена. Доступные: {$list}");
        }
        return $species;
    }

    /**
     * Файлы изображений в папке (без подпапок и служебных файлов), по имени.
     *
     * @return string[]
     */
    private function findFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if ($name[0] === '.' || !is_file($path) || str_ends_with($name, '.error.txt')) {
                continue;
            }
            // Файлы с неподходящим расширением тоже обрабатываем — они уйдут в fail с понятной причиной.
            $files[] = $path;
        }
        natcasesort($files);
        return array_values($files);
    }

    private function preview(string $file, ?TreeSpecies $defaultSpecies): string
    {
        $this->importer->checkFile($file);
        $meta = $this->importer->readMetadata($file);
        if ($meta['latitude'] === null) {
            throw new DomainException('В метаданных нет GPS-координат.');
        }
        $species = $this->importer->detectSpecies($meta) ?? $defaultSpecies;
        if (!$species) {
            throw new DomainException('Не удалось определить породу по метаданным. Укажите --species=…');
        }
        return "{$species->name}, {$meta['latitude']}, {$meta['longitude']}"
            . ($meta['date'] ? ", {$meta['date']}" : ', без даты')
            . ($meta['title'] ? ", «{$meta['title']}»" : '');
    }

    /**
     * Перемещает файл; при совпадении имени добавляет суффикс. Возвращает итоговое имя.
     */
    private function moveTo(string $file, string $dir): string
    {
        $info = pathinfo($file);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $name = $base . $ext;
        for ($n = 1; file_exists($dir . DIRECTORY_SEPARATOR . $name); $n++) {
            $name = "{$base}-{$n}{$ext}";
        }
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@rename($file, $target)) {
            if (!@copy($file, $target) || !@unlink($file)) {
                throw new \RuntimeException("не удалось переместить в {$dir} (проверьте права)");
            }
        }
        return $name;
    }

    private function writeReason(string $dir, string $name, string $reason): void
    {
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . $name . '.error.txt',
            date('Y-m-d H:i:s') . "\n" . $reason . "\n"
        );
    }
}
