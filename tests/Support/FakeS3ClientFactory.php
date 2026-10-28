<?php

namespace Tests\Support;

use App\Domain\DTO\Storage\StorageConnectionData;
use App\Services\Storage\EndpointGuard;
use App\Services\Storage\S3ClientFactory;
use Aws\MockHandler;

/**
 * Фабрика с MockHandler: ответы S3 задаются в тесте, сеть не используется.
 */
final class FakeS3ClientFactory extends S3ClientFactory
{
    /** @var list<StorageConnectionData> */
    public array $connections = [];

    public function __construct(public readonly MockHandler $handler, EndpointGuard $guard)
    {
        parent::__construct($guard, 1, 1);
    }

    protected function config(StorageConnectionData $connection): array
    {
        $this->connections[] = $connection;

        return [...parent::config($connection), 'handler' => $this->handler];
    }
}
