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
 *   yii import                         — импортировать посадки из import/plantings.json и все фото из import/in
 *   yii import --json=path/file.json   — другой файл с данными посадок
 *   yii import --species="Дуб черешчатый"  — порода для фото, у которых её нет в метаданных (название или ID)
 *   yii import --user=admin@site.ru    — от чьего имени создавать (email, телефон или ID; по умолчанию — первый администратор)
 *   yii import --dry-run               — только показать, что будет импортировано, ничего не создавая и не перемещая
 *   yii import --lat=54.6703 --lon=38.2469  — координаты для фото без GPS (и записей JSON с "coordinates": null)
 *   yii import --lat=… --lon=… --spread=8   — расстояние между такими точками, м (по умолчанию 5)
 *
 * Из метаданных берутся GPS-координаты (обязательны), дата съёмки (дата посадки),
 * заголовок и описание (XMP / IPTC / EXIF), ключевые слова — для определения породы.
 * Успешно импортированные файлы переносятся в import/success, остальные — в import/fail.
 *
 * import/plantings.json — список именных посадок. Каждая запись:
 *   {
 *     "file": "IMG_0135.HEIC",                         // фото в import/in (обязательно)
 *     "name": "Мария Филимонова",                       // имя дерева — посадка становится именной (обязательно)
 *     "coordinates": {"lat": 54.670342, "lon": 38.247131}, // null — взять из EXIF фото
 *     "species": "Дуб черешчатый",                      // необязательно: название, первое слово или ID
 *     "planted_at": "2025-05-12",                       // необязательно
 *     "description": "…",                               // необязательно
 *     "named_until": "2030-12-31",                      // необязательно, по умолчанию бессрочно
 *     "note": "…"                                       // необязательно, служебная пометка в журнал модерации
 *   }
 * Посадки из файла создаются сразу опубликованными и именными. Запись пропускается,
 * если её фото уже лежит в import/success (импортирована раньше), — повторный запуск безопасен.
 * Фото из import/in, которых нет в файле, импортируются как обычно, по метаданным.
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
    /**
     * @var string файл с данными посадок (если его нет — импорт только по метаданным фото)
     */
    public string $json = '@app/import/plantings.json';
    /**
     * @var string|null широта по умолчанию для фото без координат (например 54.6703)
     */
    public ?string $lat = null;
    /**
     * @var string|null долгота по умолчанию для фото без координат (например 38.2469)
     */
    public ?string $lon = null;
    /**
     * @var string расстояние между точками без координат, м: они раскладываются вокруг --lat/--lon, чтобы не слиться в одну
     */
    public string $spread = '5';

    public string $inPath = '@app/import/in';
    public string $successPath = '@app/import/success';
    public string $failPath = '@app/import/fail';

    public function __construct($id, $module, private readonly PhotoImportService $importer, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['species', 'user', 'dryRun', 'json', 'lat', 'lon', 'spread']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['s' => 'species', 'u' => 'user', 'n' => 'dryRun', 'j' => 'json']);
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
            $this->applyDefaultCoordinates();
            $jsonFile = Yii::getAlias($this->json);
            $entries = is_file($jsonFile) ? $this->loadJson($jsonFile) : [];
        } catch (DomainException $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        // Задания: сначала записи из JSON, затем фото из in, которых в JSON нет
        $tasks = [];
        $claimed = [];
        $seen = [];
        foreach ($entries as $entry) {
            $task = ['label' => $entry['file'] . ($entry['name'] ? " ({$entry['name']})" : ''), 'error' => $entry['error'],
                'overrides' => $entry['overrides'], 'path' => null, 'skip' => null];
            $key = strtolower($entry['file']);
            if ($task['error'] === null && isset($seen[$key])) {
                $task['error'] = 'этот файл уже указан в записи выше';
            }
            $seen[$key] = true;
            $found = $entry['file'] !== '' ? $this->locate($in, $entry['file']) : null;
            if ($found !== null) {
                // Фото с записью в JSON не импортируется повторно как обычное, даже если запись с ошибкой
                $claimed[strtolower(basename($found))] = true;
                if ($task['error'] !== null) {
                    $task['error'] .= '; фото оставлено в import/in — исправьте запись и запустите импорт снова';
                }
            }
            if ($entry['file'] !== '' && $task['error'] === null) {
                // Ошибка в самой записи — фото остаётся в in: исправьте JSON и запустите снова
                $task['path'] = $found;
                if ($task['path'] === null && $this->locate($success, $entry['file']) !== null) {
                    $task['skip'] = 'уже импортировано ранее (файл в import/success)';
                } elseif ($task['path'] === null && $this->locate($fail, $entry['file']) !== null) {
                    $task['error'] = 'фото в import/fail после прошлой ошибки (причина — в .error.txt); чтобы повторить, верните его в import/in';
                } elseif ($task['path'] === null) {
                    $task['error'] = 'файл не найден в import/in';
                }
            }
            $tasks[] = $task;
        }
        foreach ($this->findFiles($in) as $path) {
            if (!isset($claimed[strtolower(basename($path))])) {
                $tasks[] = ['label' => basename($path), 'path' => $path, 'overrides' => [], 'error' => null, 'skip' => null];
            }
        }

        if (!$tasks) {
            $this->stdout("Нет данных для импорта: папка {$in} пуста" . (is_file($jsonFile) ? ', файл ' . basename($jsonFile) . ' пуст' : '') . ".\n");
            return ExitCode::OK;
        }

        $this->stdout(($this->dryRun ? 'ПРОБНЫЙ ПРОГОН, ничего не сохраняется. ' : '')
            . 'Заданий: ' . count($tasks) . ($entries ? ' (из ' . basename($jsonFile) . ': ' . count($entries) . ')' : '')
            . '. Владелец: ' . $owner->getPrivateName() . " (ID {$owner->id})"
            . ($defaultSpecies ? ". Порода по умолчанию: {$defaultSpecies->name}" : '')
            . ($this->importer->hasDefaultCoordinates() ? ". Координаты по умолчанию: {$this->lat}, {$this->lon} (шаг {$this->spread} м)" : '')
            . "\n\n");

        $rows = [];
        $ok = $failed = $skipped = 0;
        foreach ($tasks as $i => $task) {
            $label = $task['label'];
            $file = $task['path'];
            $this->stdout(sprintf('[%d/%d] %s … ', $i + 1, count($tasks), $label));

            if ($task['skip'] !== null) {
                $skipped++;
                $this->stdout("ПРОПУЩЕНО\n", Console::FG_YELLOW);
                $rows[] = [$label, 'skip', $task['skip']];
                continue;
            }
            try {
                if ($task['error'] !== null) {
                    throw new DomainException($task['error']);
                }
                if ($this->dryRun) {
                    $details = $this->preview($file, $defaultSpecies, $task['overrides']);
                } else {
                    $planting = $this->importer->import($file, $owner, $defaultSpecies, $task['overrides']);
                    $details = "посадка #{$planting->id}" . ($planting->isNamed() ? ', именная' : '')
                        . ", {$planting->species->name}, {$planting->latitude}, {$planting->longitude}"
                        . ($this->importer->lastUsedDefaultCoordinates() ? ' (координаты по умолчанию)' : '');
                    try {
                        $moved = $this->moveTo($file, $success);
                        if ($moved !== basename($file)) {
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
                $rows[] = [$label, 'ok', $details];
            } catch (\Throwable $e) {
                $failed++;
                $reason = $e instanceof DomainException ? $e->getMessage() : get_class($e) . ': ' . $e->getMessage();
                Yii::warning("Импорт {$label}: {$reason}", __METHOD__);
                if (!$this->dryRun && $file !== null && is_file($file)) {
                    try {
                        $moved = $this->moveTo($file, $fail);
                        $this->writeReason($fail, $moved, $reason);
                    } catch (\Throwable $moveError) {
                        $reason .= ' | не удалось переместить в fail: ' . $moveError->getMessage();
                    }
                }
                $this->stdout("ОШИБКА\n", Console::FG_RED);
                $rows[] = [$label, 'fail', $reason];
            }
        }

        if ($ok > 0 && !$this->dryRun) {
            $this->importer->finish();
        }

        $this->stdout("\nРезультат импорта:\n");
        $marks = [
            'ok' => ['[OK]       ', Console::FG_GREEN, $this->dryRun ? 'будет импортировано' : 'импортировано'],
            'fail' => ['[ОШИБКА]   ', Console::FG_RED, 'не импортировано'],
            'skip' => ['[ПРОПУЩЕНО]', Console::FG_YELLOW, 'пропущено'],
        ];
        foreach ($rows as [$label, $state, $details]) {
            [$mark, $color, $status] = $marks[$state];
            $this->stdout("  {$mark} ", $color);
            $this->stdout("{$label} — {$status}: {$details}\n");
        }
        $this->stdout(sprintf("\nИтого: %d, успешно: %d, с ошибкой: %d, пропущено: %d\n", count($tasks), $ok, $failed, $skipped),
            $failed ? Console::FG_YELLOW : Console::FG_GREEN);
        if ($failed && !$this->dryRun) {
            $this->stdout("Фото с ошибкой — в {$fail}, причина — в файле <имя>.error.txt рядом.\n");
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
        return $this->findSpecies($this->species);
    }

    /**
     * --lat/--lon/--spread → координаты по умолчанию в сервисе импорта.
     * Десятичная запятая допускается: --lat=54,6703.
     */
    private function applyDefaultCoordinates(): void
    {
        $lat = $this->lat !== null ? str_replace(',', '.', trim($this->lat)) : '';
        $lon = $this->lon !== null ? str_replace(',', '.', trim($this->lon)) : '';
        if ($lat === '' && $lon === '') {
            return;
        }
        if ($lat === '' || $lon === '') {
            throw new DomainException('Координаты по умолчанию задаются парой: --lat=… --lon=…');
        }
        $spread = str_replace(',', '.', trim($this->spread));
        if (!is_numeric($lat) || !is_numeric($lon) || !is_numeric($spread)) {
            throw new DomainException('--lat, --lon и --spread должны быть числами, например --lat=54.6703 --lon=38.2469 --spread=5');
        }
        $this->lat = $lat;
        $this->lon = $lon;
        $this->spread = $spread;
        $this->importer->setDefaultCoordinates((float)$lat, (float)$lon, (float)$spread);
    }

    /**
     * Порода по ID, полному названию или первому слову названия («ольха» → «Ольха чёрная», если такая порода одна).
     */
    private function findSpecies(string $value): TreeSpecies
    {
        $value = trim($value);
        $species = ctype_digit($value)
            ? TreeSpecies::findOne((int)$value)
            : $this->importer->detectSpecies(['keywords' => [$value], 'title' => null, 'description' => null]);
        if (!$species) {
            $list = implode(', ', TreeSpecies::getList());
            throw new DomainException("Порода «{$value}» не найдена. Доступные: {$list}");
        }
        return $species;
    }

    /**
     * Читает файл с данными посадок. Ошибки отдельных записей не прерывают импорт —
     * они попадают в отчёт; ошибка формата всего файла — прерывает.
     *
     * @return array<int, array{file: string, name: string, overrides: array, error: ?string}>
     */
    private function loadJson(string $path): array
    {
        $raw = (string)file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM
        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DomainException('Файл ' . basename($path) . ' — некорректный JSON: ' . $e->getMessage());
        }
        if (isset($data['plantings']) && is_array($data['plantings'])) {
            $data = $data['plantings'];
        }
        if (!is_array($data) || !array_is_list($data)) {
            throw new DomainException('Файл ' . basename($path) . ' должен содержать массив записей [{"file": …, "name": …}, …].');
        }

        $entries = [];
        foreach ($data as $i => $item) {
            $n = $i + 1;
            $entry = ['file' => '', 'name' => '', 'overrides' => [], 'error' => null];
            if (!is_array($item)) {
                $entry['file'] = "запись №{$n}";
                $entry['error'] = 'запись должна быть объектом';
                $entries[] = $entry;
                continue;
            }
            $entry['file'] = basename(trim((string)($item['file'] ?? '')));
            $entry['name'] = trim((string)($item['name'] ?? ''));
            try {
                if ($entry['file'] === '') {
                    $entry['file'] = "запись №{$n}";
                    throw new DomainException('не указан файл (поле "file")');
                }
                $named = !array_key_exists('named', $item) || (bool)$item['named'];
                if ($named && $entry['name'] === '') {
                    throw new DomainException('не указано имя (поле "name")');
                }
                $overrides = ['named' => $named, 'title' => $entry['name'] ?: null];

                $coords = $item['coordinates'] ?? null;
                if (is_array($coords)) {
                    $lat = $coords['lat'] ?? $coords['latitude'] ?? null;
                    $lon = $coords['lon'] ?? $coords['lng'] ?? $coords['longitude'] ?? null;
                    if (!is_numeric($lat) || !is_numeric($lon) || abs((float)$lat) > 90 || abs((float)$lon) > 180) {
                        throw new DomainException('некорректные координаты (нужно {"lat": …, "lon": …})');
                    }
                    $overrides['latitude'] = (float)$lat;
                    $overrides['longitude'] = (float)$lon;
                }
                if (!empty($item['species'])) {
                    $overrides['species'] = $this->findSpecies((string)$item['species']);
                }
                if (!empty($item['planted_at'])) {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$item['planted_at']);
                    if (!$date) {
                        throw new DomainException('дата посадки должна быть в формате ГГГГ-ММ-ДД');
                    }
                    $overrides['planted_at'] = $date->format('Y-m-d');
                }
                if (!empty($item['named_until'])) {
                    $until = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$item['named_until']);
                    if (!$until) {
                        throw new DomainException('named_until должна быть в формате ГГГГ-ММ-ДД');
                    }
                    $overrides['named_until'] = $until->setTime(23, 59, 59)->getTimestamp();
                }
                if (!empty($item['description'])) {
                    $overrides['description'] = (string)$item['description'];
                }
                if (!empty($item['note'])) {
                    $overrides['comment'] = (string)$item['note'];
                }
                $entry['overrides'] = $overrides;
            } catch (DomainException $e) {
                $entry['error'] = $e->getMessage();
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Файл в папке по имени; если точного совпадения нет — без учёта регистра
     * (IMG_0135.HEIC и img_0135.heic — на Linux это разные имена).
     */
    private function locate(string $dir, string $name): ?string
    {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            return $path;
        }
        foreach (scandir($dir) ?: [] as $candidate) {
            if (strcasecmp($candidate, $name) === 0 && is_file($dir . DIRECTORY_SEPARATOR . $candidate)) {
                return $dir . DIRECTORY_SEPARATOR . $candidate;
            }
        }
        return null;
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

    private function preview(string $file, ?TreeSpecies $defaultSpecies, array $overrides = []): string
    {
        $data = $this->importer->prepare($file, $defaultSpecies, $overrides);
        $meta = $data['meta'];
        return ($data['named'] ? 'именная, ' : '') . "{$data['species']->name}, {$meta['latitude']}, {$meta['longitude']}"
            . ($data['coordsFromDefault'] ? ' (по умолчанию)' : ($data['coordsFromData'] ? ' (из JSON)' : ' (из EXIF)'))
            . ($data['plantedAt'] ? ", {$data['plantedAt']}" : ', без даты')
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
