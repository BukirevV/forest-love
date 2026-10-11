<?php

namespace app\components\storage;

use app\helpers\Uuid;
use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

/**
 * Хранение на локальном диске.
 * Закрытая зона — вне web/ (@storage), публичная — web/uploads.
 * В каталоге загрузок исполнение скриптов запрещается на уровне веб-сервера (см. deploy/nginx.conf).
 */
class LocalStorage extends Component implements StorageInterface
{
    public string $privatePath = '@storage';
    public string $publicPath = '@webroot/uploads';
    public string $publicUrl = '@web/uploads';

    private function privateRoot(): string
    {
        return rtrim(Yii::getAlias($this->privatePath), '/');
    }

    private function publicRoot(): string
    {
        return rtrim(Yii::getAlias($this->publicPath), '/');
    }

    private function buildPath(string $dir, string $extension): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension)) ?: 'bin';
        return trim($dir, '/') . '/' . date('Y') . '/' . date('m') . '/' . Uuid::v4() . '.' . $extension;
    }

    private function safe(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Недопустимый путь файла.');
        }
        return $path;
    }

    private function copy(string $source, string $root, string $relative): void
    {
        $target = $root . '/' . $relative;
        FileHelper::createDirectory(dirname($target), 0775);
        if (is_uploaded_file($source) ? !move_uploaded_file($source, $target) : !copy($source, $target)) {
            throw new \RuntimeException('Не удалось сохранить файл.');
        }
        @chmod($target, 0664);
    }

    public function savePrivate(string $sourcePath, string $dir, string $extension): string
    {
        $relative = $this->buildPath($dir, $extension);
        $this->copy($sourcePath, $this->privateRoot(), $relative);
        return $relative;
    }

    public function savePublicFile(string $sourcePath, string $dir, string $extension): string
    {
        $relative = $this->buildPath($dir, $extension);
        $this->copy($sourcePath, $this->publicRoot(), $relative);
        return $relative;
    }

    public function getPrivateLocalPath(string $path): string
    {
        return $this->privateRoot() . '/' . $this->safe($path);
    }

    public function getPublicUrl(string $path): string
    {
        return rtrim(Yii::getAlias($this->publicUrl), '/') . '/' . $this->safe($path);
    }

    public function deletePrivate(string $path): bool
    {
        $file = $this->privateRoot() . '/' . $this->safe($path);
        return !is_file($file) || @unlink($file);
    }

    public function deletePublic(string $path): bool
    {
        $file = $this->publicRoot() . '/' . $this->safe($path);
        return !is_file($file) || @unlink($file);
    }

    public function existsPrivate(string $path): bool
    {
        return is_file($this->privateRoot() . '/' . $this->safe($path));
    }

    public function listFiles(bool $private, string $dir = ''): iterable
    {
        $root = $private ? $this->privateRoot() : $this->publicRoot();
        $base = $root . ($dir !== '' ? '/' . $this->safe($dir) : '');
        if (!is_dir($base)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && !str_starts_with($file->getFilename(), '.')) {
                yield ltrim(substr($file->getPathname(), strlen($root)), '/');
            }
        }
    }

    public function getModifiedTime(bool $private, string $path): ?int
    {
        $file = ($private ? $this->privateRoot() : $this->publicRoot()) . '/' . $this->safe($path);
        return is_file($file) ? (int)filemtime($file) : null;
    }
}
