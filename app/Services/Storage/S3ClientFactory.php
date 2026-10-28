<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\DTO\Storage\StorageConnectionData;
use Aws\S3\S3Client;

/**
 * Клиент создаётся на каждый вызов и не кешируется: под Octane он не должен пережить запрос
 * и не должен смешивать учётные данные разных хранилищ.
 */
class S3ClientFactory
{
    public function __construct(
        private readonly EndpointGuard $guard,
        private readonly float $connectTimeout,
        private readonly float $timeout,
    ) {}

    public function make(StorageConnectionData $connection): S3Client
    {
        $this->guard->assertAllowed($connection->endpoint);

        return new S3Client($this->config($connection));
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(StorageConnectionData $connection): array
    {
        return array_filter([
            'version' => 'latest',
            'region' => $connection->region,
            'endpoint' => $connection->endpoint,
            'use_path_style_endpoint' => $connection->usePathStyle,
            'credentials' => [
                'key' => $connection->accessKeyId,
                'secret' => $connection->secretAccessKey,
            ],
            'http' => [
                'verify' => $connection->verifySsl,
                'connect_timeout' => $this->connectTimeout,
                'timeout' => $this->timeout,
            ],
            'retries' => 1,
            // Новые версии SDK по умолчанию добавляют CRC32-суммы, которые многие S3-совместимые хранилища не принимают
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ], fn (mixed $value) => $value !== null);
    }
}
