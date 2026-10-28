<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Хостинг статического сайта: либо индексный документ (и страница ошибки), либо перенаправление всех запросов.
 */
final class WebsiteData extends DTO
{
    public ?string $indexDocument = null;

    public ?string $errorDocument = null;

    public ?string $redirectHost = null;

    /** http или https; null — тот же протокол, что у запроса */
    public ?string $redirectProtocol = null;

    /** Правила маршрутизации (RoutingRules) как JSON; null — правил нет */
    public ?string $routingRules = null;

    /** Адрес сайта, если его можно вычислить для провайдера */
    public ?string $url = null;
}
