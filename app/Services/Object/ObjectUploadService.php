<?php

declare(strict_types=1);

namespace App\Services\Object;

use App\Exceptions\DomainException;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGateway;
use App\Services\Storage\StorageGatewayFactory;
use Closure;
use Throwable;

/**
 * Загрузка через сервер. Небольшие файлы — одним запросом, крупные — частями (S3 multipart):
 * браузер присылает части по очереди, сервер передаёт каждую в хранилище потоком из временного файла.
 * Загрузки в журнал не пишутся.
 */
final readonly class ObjectUploadService
{
    /** Размер части, которую присылает браузер; S3 требует не меньше 5 МБ для всех частей, кроме последней */
    public const int PART_SIZE = 16 * 1024 * 1024;

    public function __construct(
        private StorageGatewayFactory $gateways,
        private StorageErrorTranslator $errors,
    ) {}

    public function put(int $storageId, string $bucket, string $key, string $path, ?string $contentType): void
    {
        $this->call($storageId, function (StorageGateway $gateway) use ($bucket, $key, $path, $contentType): void {
            $body = $this->open($path);

            try {
                $gateway->putObject($bucket, $key, $body, $contentType);
            } finally {
                fclose($body);
            }
        });
    }

    public function start(int $storageId, string $bucket, string $key, ?string $contentType): string
    {
        return $this->call($storageId, fn (StorageGateway $gateway) => $gateway->createMultipartUpload($bucket, $key, $contentType));
    }

    public function part(int $storageId, string $bucket, string $key, string $uploadId, int $partNumber, string $path): string
    {
        return $this->call($storageId, function (StorageGateway $gateway) use ($bucket, $key, $uploadId, $partNumber, $path): string {
            $body = $this->open($path);

            try {
                return $gateway->uploadPart($bucket, $key, $uploadId, $partNumber, $body);
            } finally {
                fclose($body);
            }
        });
    }

    /**
     * @param  list<array{partNumber: int, etag: string}>  $parts
     */
    public function complete(int $storageId, string $bucket, string $key, string $uploadId, array $parts): void
    {
        usort($parts, fn (array $a, array $b) => $a['partNumber'] <=> $b['partNumber']);

        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->completeMultipartUpload($bucket, $key, $uploadId, $parts));
    }

    /**
     * Отмена своей загрузки (пользователь нажал «Отмена» или загрузка упала). Ошибки не важны: загрузку уберёт и правило жизненного цикла.
     */
    public function abort(int $storageId, string $bucket, string $key, string $uploadId): void
    {
        try {
            $this->gateways->forStorage($storageId)->abortUpload($bucket, $key, $uploadId);
        } catch (Throwable) {
            // уже отменена или завершена
        }
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        $body = fopen($path, 'rb');

        if ($body === false) {
            throw new DomainException(__('Could not read the uploaded file.'));
        }

        return $body;
    }

    /**
     * @template T
     *
     * @param  Closure(StorageGateway): T  $call
     * @return T
     */
    private function call(int $storageId, Closure $call): mixed
    {
        try {
            return $call($this->gateways->forStorage($storageId));
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException(__('Upload failed: :error', ['error' => $this->errors->message($e)]), previous: $e);
        }
    }
}
