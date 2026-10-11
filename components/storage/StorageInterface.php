<?php

namespace app\components\storage;

/**
 * Хранилище файлов (ТЗ 9.1, 18): закрытая зона для оригиналов, публичная — для копий и превью.
 * Пути — относительные; реализация может быть заменена на S3-совместимую.
 */
interface StorageInterface
{
    /** Сохраняет файл в закрытое хранилище, возвращает относительный путь. */
    public function savePrivate(string $sourcePath, string $dir, string $extension): string;

    /** Сохраняет файл в публичное хранилище ({dir}/{yyyy}/{mm}/{uuid}.{ext}), возвращает относительный путь. */
    public function savePublicFile(string $sourcePath, string $dir, string $extension): string;

    /** Локальный путь к закрытому файлу для чтения (для S3 — временная копия). */
    public function getPrivateLocalPath(string $path): string;

    public function getPublicUrl(string $path): string;

    public function deletePrivate(string $path): bool;

    public function deletePublic(string $path): bool;

    public function existsPrivate(string $path): bool;

    /**
     * Перечень всех файлов (для cleanup/orphan-files).
     *
     * @return iterable<string> относительные пути
     */
    public function listFiles(bool $private, string $dir = ''): iterable;

    /** Время изменения файла (unix time) или null. */
    public function getModifiedTime(bool $private, string $path): ?int;
}
