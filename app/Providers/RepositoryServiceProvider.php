<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Permission\PermissionTemplateRepository;
use App\Repositories\Permission\PermissionTemplateRepositoryInterface;
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
    ];
}
