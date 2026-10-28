<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\DTO\Object\ObjectReferenceData;
use App\Http\Requests\Bucket\BucketRequest;

/**
 * Действие над объектами бакета. Ключ объекта передаётся параметром (в пути он неудобен: содержит «/»).
 */
abstract class ObjectRequest extends BucketRequest
{
    /** Ключ объекта в S3 — до 1024 байт */
    protected const array KEY = ['required', 'string', 'max:1024'];

    protected const array VERSION = ['nullable', 'string', 'max:1024'];

    protected const array PREFIX = ['nullable', 'string', 'max:1024'];

    public function objectKey(): string
    {
        return (string) $this->validated('key');
    }

    public function versionId(): ?string
    {
        $version = $this->validated('version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function reference(): ObjectReferenceData
    {
        return new ObjectReferenceData(['key' => $this->objectKey(), 'versionId' => $this->versionId()]);
    }

    /**
     * «Папка»: пусто — корень бакета, иначе префикс с «/» на конце.
     */
    public function prefix(string $field = 'prefix'): string
    {
        $prefix = (string) $this->validated($field);

        return $prefix === '' || str_ends_with($prefix, '/') ? $prefix : $prefix.'/';
    }
}
