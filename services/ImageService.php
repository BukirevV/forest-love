<?php

namespace app\services;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Yii;
use yii\helpers\FileHelper;
use yii\imagine\Image;

/**
 * Обработка изображений: автоповорот по EXIF, ресайз, превью, HEIC→JPEG,
 * удаление всех метаданных из публичной копии.
 */
class ImageService
{
    public function __construct(private readonly ExifService $exif)
    {
    }

    /**
     * Определение MIME по содержимому (а не по расширению).
     */
    public static function detectMime(string $path): string
    {
        return (string)FileHelper::getMimeType($path, null, false);
    }

    public static function isAllowedMime(string $mime): bool
    {
        return in_array($mime, Yii::$app->params['upload.allowedMimeTypes'] ?? [], true);
    }

    /**
     * Создаёт публичную копию и превью во временных файлах.
     *
     * @return array{public: string, thumb: string, width: int, height: int}
     */
    public function process(string $sourcePath): array
    {
        $params = Yii::$app->params;
        $maxSide = (int)($params['image.maxSide'] ?? 1600);
        $thumbSide = (int)($params['image.thumbSide'] ?? 400);
        $quality = (int)($params['image.quality'] ?? 85);

        $mime = self::detectMime($sourcePath);
        $source = in_array($mime, ['image/heic', 'image/heif'], true) ? $this->convertHeic($sourcePath) : $sourcePath;

        $orientation = $this->exif->extract($sourcePath)['orientation'] ?? 1;

        $imagine = $this->imagine();
        $image = $imagine->open($source);
        $image = $this->autoRotate($image, $orientation);
        $image->strip(); // удаление EXIF/IPTC/XMP

        $public = $this->fit($image->copy(), $maxSide);
        $thumb = $this->fit($image->copy(), $thumbSide);

        $tmpDir = Yii::getAlias('@runtime/image-tmp');
        FileHelper::createDirectory($tmpDir);
        $publicPath = $tmpDir . '/' . uniqid('p_', true) . '.jpg';
        $thumbPath = $tmpDir . '/' . uniqid('t_', true) . '.jpg';

        $options = ['jpeg_quality' => $quality, 'format' => 'jpg'];
        $public->save($publicPath, $options);
        $thumb->save($thumbPath, ['jpeg_quality' => 80] + $options);

        $size = $public->getSize();

        if ($source !== $sourcePath) {
            @unlink($source);
        }

        return [
            'public' => $publicPath,
            'thumb' => $thumbPath,
            'width' => $size->getWidth(),
            'height' => $size->getHeight(),
        ];
    }

    /**
     * Размеры оригинала (без полной обработки).
     *
     * @return array{0:int, 1:int}
     */
    public function getSize(string $path): array
    {
        $info = @getimagesize($path);
        return $info ? [(int)$info[0], (int)$info[1]] : [0, 0];
    }

    private function imagine(): ImagineInterface
    {
        return Image::getImagine();
    }

    private function fit(ImageInterface $image, int $maxSide): ImageInterface
    {
        $size = $image->getSize();
        if (max($size->getWidth(), $size->getHeight()) <= $maxSide) {
            return $image;
        }
        $ratio = $maxSide / max($size->getWidth(), $size->getHeight());
        return $image->resize(new Box(
            max(1, (int)round($size->getWidth() * $ratio)),
            max(1, (int)round($size->getHeight() * $ratio))
        ), ImageInterface::FILTER_LANCZOS);
    }

    /**
     * Поворот/отражение по тегу EXIF Orientation (1–8).
     */
    private function autoRotate(ImageInterface $image, int $orientation): ImageInterface
    {
        switch ($orientation) {
            case 2: $image->flipHorizontally(); break;
            case 3: $image->rotate(180); break;
            case 4: $image->flipVertically(); break;
            case 5: $image->flipHorizontally()->rotate(90); break;
            case 6: $image->rotate(90); break;
            case 7: $image->flipHorizontally()->rotate(-90); break;
            case 8: $image->rotate(-90); break;
        }
        return $image;
    }

    /**
     * HEIC → JPEG через Imagick (требуется libheif). Возвращает путь к временному JPEG.
     */
    private function convertHeic(string $path): string
    {
        if (!class_exists(\Imagick::class) || !in_array('HEIC', \Imagick::queryFormats('HEIC'), true)) {
            throw new \RuntimeException('Конвертация HEIC недоступна на сервере (нужен Imagick с поддержкой libheif).');
        }
        $tmp = Yii::getAlias('@runtime/image-tmp');
        FileHelper::createDirectory($tmp);
        $target = $tmp . '/' . uniqid('heic_', true) . '.jpg';
        $im = new \Imagick($path);
        $im->setImageFormat('jpeg');
        $im->writeImage($target);
        $im->clear();
        return $target;
    }
}
