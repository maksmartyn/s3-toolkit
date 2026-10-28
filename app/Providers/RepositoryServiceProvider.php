<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Audit\AuditLogRepository;
use App\Repositories\Audit\AuditLogRepositoryInterface;
use App\Repositories\Bucket\BucketStatsRepository;
use App\Repositories\Bucket\BucketStatsRepositoryInterface;
use App\Repositories\Operation\OperationRepository;
use App\Repositories\Operation\OperationRepositoryInterface;
use App\Repositories\Permission\PermissionTemplateRepository;
use App\Repositories\Permission\PermissionTemplateRepositoryInterface;
use App\Repositories\Storage\StorageAccessRepository;
use App\Repositories\Storage\StorageAccessRepositoryInterface;
use App\Repositories\Storage\StorageRepository;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Интерфейс репозитория => реализация.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        UserRepositoryInterface::class => UserRepository::class,
        PermissionTemplateRepositoryInterface::class => PermissionTemplateRepository::class,
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        StorageRepositoryInterface::class => StorageRepository::class,
        StorageAccessRepositoryInterface::class => StorageAccessRepository::class,
        OperationRepositoryInterface::class => OperationRepository::class,
        BucketStatsRepositoryInterface::class => BucketStatsRepository::class,
    ];
}
