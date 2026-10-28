<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Интерфейс репозитория => реализация.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [];
}
