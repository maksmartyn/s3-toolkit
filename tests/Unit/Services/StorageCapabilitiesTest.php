<?php

use App\Domain\Enum\Storage\StorageCapability;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;
use App\Services\Storage\StorageCapabilities;

it('uses provider defaults', function () {
    $capabilities = new StorageCapabilities;

    expect($capabilities->defaults(StorageProvider::Generic))->toBe(StorageCapability::cases())
        ->and($capabilities->defaults(StorageProvider::CloudflareR2))->toBe([
            StorageCapability::Cors,
            StorageCapability::Lifecycle,
            StorageCapability::MultipartUploads,
        ])
        ->and($capabilities->defaults(StorageProvider::Minio))->not->toContain(StorageCapability::Acl);
});

it('stores only differences from defaults', function () {
    $capabilities = new StorageCapabilities;
    $defaults = $capabilities->defaults(StorageProvider::CloudflareR2);

    expect($capabilities->overrides(StorageProvider::CloudflareR2, $defaults))->toBeNull()
        ->and($capabilities->overrides(StorageProvider::CloudflareR2, [StorageCapability::Cors, StorageCapability::Versioning]))
        ->toBe(['versioning' => true, 'lifecycle' => false, 'multipart_uploads' => false]);
});

it('applies overrides on top of defaults', function () {
    $storage = new Storage(['provider' => StorageProvider::Minio, 'capabilities' => ['acl' => true, 'versioning' => false]]);

    $enabled = (new StorageCapabilities)->enabled($storage);

    expect($enabled)->toContain(StorageCapability::Acl, StorageCapability::ObjectLock)
        ->not->toContain(StorageCapability::Versioning, StorageCapability::Website);
});
