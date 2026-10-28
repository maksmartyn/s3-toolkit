<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Repositories\Storage\StorageRepositoryInterface;

final readonly class StorageGatewayFactory
{
    public function __construct(
        private StorageRepositoryInterface $storages,
        private StorageMapper $mapper,
        private StorageCapabilities $capabilities,
        private S3ClientFactory $clients,
    ) {}

    public function forStorage(int $storageId): StorageGateway
    {
        $storage = $this->storages->findOrFail($storageId);
        $connection = $this->mapper->toConnection($storage);

        return new StorageGateway(
            client: $this->clients->make($connection),
            provider: $storage->provider,
            region: $storage->region,
            capabilities: $this->capabilities->enabled($storage),
            fixedBuckets: $storage->buckets,
        );
    }
}
