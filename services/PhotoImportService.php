<?php

namespace app\services;

use app\components\storage\StorageInterface;
use app\enums\CoordsSource;
use app\enums\ModerationAction;
use app\enums\PhotoSource;
use app\enums\PhotoStatus;
use app\enums\PlantingStatus;
use app\jobs\ProcessPhotoJob;
use app\models\ModerationLog;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\TreeSpecies;
use app\models\User;
use DomainException;
use Yii;

/**
 * Импорт посадок из фотографий: координаты, дата, подпись и порода берутся из метаданных снимка
 * (EXIF, IPTC, XMP). Карточка создаётся от имени указанного пользователя и сразу публикуется.
 */
class PhotoImportService
{
    /** Описания, которые камеры пишут сами, — это не подпись к фото. */
    private const JUNK_TEXT = '/^(olympus digital camera|sony dsc|dcim.*|default|image|picture|photo|untitled|\s*)$/iu';

    public function __construct(
        private readonly ExifService $exif,
        private readonly MapDataService $mapData,
    ) {
    }

    private function storage(): StorageInterface
    {
        return Yii::$app->get('storage');
    }

    /**
     * Метаданные снимка, нужные для карточки.
     *
     * @return array{latitude: ?float, longitude: ?float, date: ?string, title: ?string, description: ?string, keywords: string[]}
     */
    public function readMetadata(string $path): array
    {
        $exif = $this->exif->extract($path);
        $meta = [
            'latitude' => $exif['latitude'],
            'longitude' => $exif['longitude'],
            'date' => $exif['date'],
            'title' => null,
            'description' => null,
            'keywords' => [],
        ];

        $iptc = $this->readIptc($path);
        $xmp = $this->readXmp($path);
        // Поля Windows (XPTitle, XPKeywords…) хранятся в UTF-16LE. Встроенная перекодировка PHP портит кириллицу,
        // поэтому просим отдать байты как есть и перекодируем сами (winText).
        $ini = ['exif.encode_unicode', 'exif.decode_unicode_intel', 'exif.decode_unicode_motorola'];
        $saved = array_combine($ini, array_map('ini_get', $ini));
        foreach ($ini as $key) {
            @ini_set($key, 'UCS-2LE');
        }
        try {
            $raw = function_exists('exif_read_data') ? (@exif_read_data($path, 'ANY_TAG', true) ?: []) : [];
        } finally {
            foreach ($saved as $key => $value) {
                @ini_set($key, (string)$value);
            }
        }
        $ifd = $raw['IFD0'] ?? [];

        $meta['title'] = $this->firstText([
            $xmp['title'] ?? null,
            $iptc['2#005'][0] ?? null,
            $this->winText($ifd['Title'] ?? $ifd['XPTitle'] ?? null),
        ]);
        $meta['description'] = $this->firstText([
            $xmp['description'] ?? null,
            $iptc['2#120'][0] ?? null,
            $ifd['ImageDescription'] ?? null,
            $this->winText($ifd['Comments'] ?? $ifd['XPComment'] ?? null),
            $this->winText($ifd['Subject'] ?? $ifd['XPSubject'] ?? null),
        ]);

        $keywords = array_merge(
            $xmp['keywords'] ?? [],
            $iptc['2#025'] ?? [],
            preg_split('/\s*[;,]\s*/u', (string)$this->winText($ifd['Keywords'] ?? $ifd['XPKeywords'] ?? null)) ?: []
        );
        $meta['keywords'] = array_values(array_unique(array_filter(array_map(
            fn($k) => $this->cleanText((string)$k),
            $keywords
        ))));

        return $meta;
    }

    /**
     * Порода по метаданным. По порядку:
     *  1) ключевое слово совпадает с полным названием (русским или латинским);
     *  2) ключевое слово — первое слово названия («дуб», «Quercus»), если такая порода одна;
     *  3) полное название встречается в подписи, описании или ключевых словах (берётся самое длинное);
     *  4) первое слово названия встречается в тексте, если такая порода одна.
     * Регистр и «ё/е» не учитываются.
     */
    public function detectSpecies(array $meta): ?TreeSpecies
    {
        /** @var TreeSpecies[] $species */
        $species = TreeSpecies::find()->where(['is_active' => true])->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])->all();
        $names = [];   // id => [полные названия]
        $genera = [];  // первое слово => [id]
        foreach ($species as $s) {
            foreach (array_filter([$s->name, $s->latin_name]) as $name) {
                $full = $this->norm($name);
                $names[$s->id][] = $full;
                $genus = explode(' ', $full)[0];
                $genera[$genus][$s->id] = $s->id;
            }
        }
        $byId = [];
        foreach ($species as $s) {
            $byId[$s->id] = $s;
        }
        $keywords = array_map(fn($k) => $this->norm($k), $meta['keywords']);

        foreach ($keywords as $k) {
            foreach ($names as $id => $list) {
                if (in_array($k, $list, true)) {
                    return $byId[$id];
                }
            }
        }
        foreach ($keywords as $k) {
            if (isset($genera[$k]) && count($genera[$k]) === 1) {
                return $byId[reset($genera[$k])];
            }
        }

        $text = $this->norm(implode(' ', array_filter([$meta['title'], $meta['description'], implode(' ', $meta['keywords'])])));
        if ($text === '') {
            return null;
        }
        $best = null;
        $bestLen = 0;
        foreach ($names as $id => $list) {
            foreach ($list as $name) {
                if (mb_strlen($name) > $bestLen && $this->containsWord($text, $name)) {
                    $best = $byId[$id];
                    $bestLen = mb_strlen($name);
                }
            }
        }
        if ($best) {
            return $best;
        }
        $found = [];
        foreach ($genera as $genus => $ids) {
            if (mb_strlen($genus) >= 3 && $this->containsWord($text, $genus)) {
                $found += $ids;
            }
        }
        return count($found) === 1 ? $byId[reset($found)] : null;
    }

    /**
     * Проверка файла: читается, формат изображения, размер. Возвращает MIME-тип.
     *
     * @throws DomainException
     */
    public function checkFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new DomainException('Файл недоступен для чтения.');
        }
        $mime = ImageService::detectMime($path);
        if (!ImageService::isAllowedMime($mime)) {
            throw new DomainException("Неподдерживаемый формат ({$mime}). Разрешены JPEG, PNG, WebP, HEIC.");
        }
        $maxSize = (int)(Yii::$app->params['upload.maxFileSize'] ?? 0);
        if ($maxSize > 0 && filesize($path) > $maxSize) {
            throw new DomainException('Файл больше допустимого размера (' . round($maxSize / 1048576) . ' МБ).');
        }
        return $mime;
    }

    /**
     * Создаёт опубликованную карточку из файла. Всё в одной транзакции: при любой ошибке
     * в базе и хранилище ничего не остаётся.
     *
     * @param TreeSpecies|null $defaultSpecies порода, если её не удалось определить по метаданным
     * @throws DomainException с понятной причиной, если импорт невозможен
     */
    public function import(string $path, User $owner, ?TreeSpecies $defaultSpecies = null): Planting
    {
        $mime = $this->checkFile($path);
        $meta = $this->readMetadata($path);
        if ($meta['latitude'] === null || $meta['longitude'] === null) {
            throw new DomainException('В метаданных нет GPS-координат.');
        }
        $species = $this->detectSpecies($meta) ?? $defaultSpecies;
        if ($species === null) {
            throw new DomainException('Не удалось определить породу по метаданным. Укажите породу по умолчанию: --species=…');
        }

        $takenAt = $meta['date'] ?? date('Y-m-d', (int)filemtime($path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
        $size = @getimagesize($path) ?: [0, 0];

        $storedPath = null;
        $photo = null;
        $tx = Yii::$app->db->beginTransaction();
        try {
            $planting = new Planting([
                'user_id' => $owner->id,
                'species_id' => $species->id,
                'latitude' => $meta['latitude'],
                'longitude' => $meta['longitude'],
                'coords_source' => CoordsSource::Exif->value,
                'planted_at' => $meta['date'],
                'title' => $meta['title'] !== null ? mb_substr($meta['title'], 0, 150) : null,
                'description' => $meta['description'] !== null ? mb_substr($meta['description'], 0, 5000) : null,
                'status' => PlantingStatus::Approved->value,
                'approved_at' => time(),
                'moderated_by' => $owner->id,
            ]);
            if (!$planting->save()) {
                throw new DomainException('Карточка: ' . implode(' ', $planting->getFirstErrors()));
            }

            $storedPath = $this->storage()->savePrivate($path, 'plantings', $extension);
            $photo = new PlantingPhoto([
                'planting_id' => $planting->id,
                'user_id' => $owner->id,
                'file_path' => $storedPath,
                'taken_at' => $takenAt,
                'exif_latitude' => $meta['latitude'],
                'exif_longitude' => $meta['longitude'],
                'source' => PhotoSource::Upload->value,
                'width' => (int)$size[0],
                'height' => (int)$size[1],
                'file_size' => (int)filesize($path),
                'mime_type' => $mime,
                'status' => PhotoStatus::Approved->value,
                'moderated_by' => $owner->id,
            ]);
            if (!$photo->save()) {
                throw new DomainException('Фото: ' . implode(' ', $photo->getFirstErrors()));
            }

            $planting->main_photo_id = $photo->id;
            $planting->save(false, ['main_photo_id', 'updated_at']);

            // Публичная копия и превью — сразу, а не через очередь: битый файл должен уйти в fail.
            (new ProcessPhotoJob(['photoId' => $photo->id]))->execute(null);
            $photo->refresh();

            ModerationLog::log($planting, ModerationAction::Approve, 'Импорт из файла ' . basename($path), $owner->id);
            $tx->commit();
        } catch (\Throwable $e) {
            if ($photo !== null && !$photo->isNewRecord) {
                $fresh = PlantingPhoto::findOne($photo->id);
                foreach ([$fresh?->public_path, $fresh?->thumb_path] as $public) {
                    if ($public) {
                        $this->storage()->deletePublic($public);
                    }
                }
            }
            $tx->rollBack();
            if ($storedPath !== null) {
                $this->storage()->deletePrivate($storedPath);
            }
            if ($e instanceof DomainException) {
                throw $e;
            }
            throw new DomainException($e->getMessage(), 0, $e);
        }

        return $planting;
    }

    /**
     * Сбросить кэш карты после пакетного импорта.
     */
    public function finish(): void
    {
        $this->mapData->invalidate();
    }

    // ------------------------------------------------------------------ helpers

    private function readIptc(string $path): array
    {
        $info = [];
        if (!@getimagesize($path, $info) || empty($info['APP13']) || !function_exists('iptcparse')) {
            return [];
        }
        $iptc = @iptcparse($info['APP13']) ?: [];
        foreach ($iptc as $tag => $values) {
            $iptc[$tag] = array_map(fn($v) => $this->toUtf8((string)$v), $values);
        }
        return $iptc;
    }

    /**
     * Простое чтение XMP-пакета (Lightroom, Google Photos, телефоны): dc:title, dc:description, dc:subject.
     */
    private function readXmp(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return [];
        }
        $data = (string)fread($handle, 512 * 1024);
        fclose($handle);
        $start = strpos($data, '<x:xmpmeta');
        $end = $start === false ? false : strpos($data, '</x:xmpmeta>', $start);
        if ($start === false || $end === false) {
            return [];
        }
        $xml = substr($data, $start, $end - $start);

        $alt = static function (string $tag) use ($xml): ?string {
            if (preg_match('#<dc:' . $tag . '\b[^>]*>.*?<rdf:li\b[^>]*>(.*?)</rdf:li>#su', $xml, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            return null;
        };
        $keywords = [];
        if (preg_match('#<dc:subject\b[^>]*>(.*?)</dc:subject>#su', $xml, $m)
            && preg_match_all('#<rdf:li\b[^>]*>(.*?)</rdf:li>#su', $m[1], $items)) {
            $keywords = array_map(static fn($k) => html_entity_decode($k, ENT_QUOTES | ENT_XML1, 'UTF-8'), $items[1]);
        }
        return ['title' => $alt('title'), 'description' => $alt('description'), 'keywords' => $keywords];
    }

    /** Поля Windows (XPTitle, XPComment, XPKeywords) — UTF-16LE. */
    private function winText(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode('', array_map('chr', $value));
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
        return rtrim($value, "\0");
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
    }

    private function cleanText(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $this->toUtf8($value)) ?? '');
        return preg_replace('/\s{2,}/u', ' ', $value) ?? '';
    }

    private function firstText(array $candidates): ?string
    {
        foreach ($candidates as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = $this->cleanText($value);
            if ($value !== '' && !preg_match(self::JUNK_TEXT, $value)) {
                return $value;
            }
        }
        return null;
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
        return str_replace('ё', 'е', $value);
    }

    /** Фраза входит в текст отдельным словом (без учёта падежных окончаний). */
    private function containsWord(string $text, string $phrase): bool
    {
        return (bool)preg_match('/(?<!\p{L})' . preg_quote($phrase, '/') . '(?!\p{L})/u', $text);
    }
}
