<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AccessKey\Support\McRunner;
use App\Services\Storage\EndpointGuard;
use App\Services\Storage\S3ClientFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EndpointGuard::class, fn (Application $app) => new EndpointGuard(
            allowPrivate: (bool) $app->make('config')->get('s3toolkit.storage.allow_private_endpoints'),
        ));

        $this->app->singleton(S3ClientFactory::class, fn (Application $app) => new S3ClientFactory(
            guard: $app->make(EndpointGuard::class),
            connectTimeout: (float) $app->make('config')->get('s3toolkit.storage.connect_timeout'),
            timeout: (float) $app->make('config')->get('s3toolkit.storage.timeout'),
        ));

        // Не синглтон: в тестах фабрику процессов подменяют
        $this->app->bind(McRunner::class, fn (Application $app) => new McRunner(
            processes: $app->make(ProcessFactory::class),
            binary: (string) $app->make('config')->get('s3toolkit.access_keys.mc_binary'),
            timeout: (int) $app->make('config')->get('s3toolkit.access_keys.mc_timeout'),
        ));
    }
}
