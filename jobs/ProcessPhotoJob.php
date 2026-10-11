<?php

namespace app\jobs;

use app\components\storage\StorageInterface;
use app\models\PlantingPhoto;
use app\services\ImageService;
use Yii;
use yii\base\BaseObject;
use yii\queue\RetryableJobInterface;

/**
 * Обработка загруженного изображения: автоповорот, ресайз, превью, очистка EXIF.
 * Результат — публичная копия и превью в публичном хранилище.
 */
class ProcessPhotoJob extends BaseObject implements RetryableJobInterface
{
    public int $photoId = 0;

    public function execute($queue): void
    {
        $photo = PlantingPhoto::findOne($this->photoId);
        if (!$photo) {
            return; // фото удалено до обработки
        }
        /** @var StorageInterface $storage */
        $storage = Yii::$app->get('storage');
        $images = Yii::$container->get(ImageService::class);

        $result = $images->process($storage->getPrivateLocalPath($photo->file_path));
        try {
            $oldPublic = $photo->public_path;
            $oldThumb = $photo->thumb_path;

            $photo->public_path = $storage->savePublicFile($result['public'], 'plantings', 'jpg');
            $photo->thumb_path = $storage->savePublicFile($result['thumb'], 'plantings/thumbs', 'jpg');
            $photo->width = $result['width'];
            $photo->height = $result['height'];
            $photo->save(false, ['public_path', 'thumb_path', 'width', 'height', 'updated_at']);

            foreach ([$oldPublic, $oldThumb] as $old) {
                if ($old) {
                    $storage->deletePublic($old);
                }
            }
        } finally {
            @unlink($result['public']);
            @unlink($result['thumb']);
        }
    }

    public function getTtr(): int
    {
        return 300;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt < 3 && !($error instanceof \RuntimeException && str_contains($error->getMessage(), 'HEIC'));
    }
}
