<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * Кто и откуда действует.
 *
 * Значения читаются из текущего контейнера в момент записи и не хранятся в полях:
 * под Octane у каждого запроса своя копия контейнера, а сервисы могут жить дольше запроса.
 */
final class AuditContext
{
    public const string SOURCE_WEB = 'web';

    public const string SOURCE_SYSTEM = 'system';

    public function actor(): ?User
    {
        $user = Container::getInstance()->make('auth')->guard('web')->user();

        return $user instanceof User ? $user : null;
    }

    public function source(): string
    {
        return $this->request()?->route() !== null ? self::SOURCE_WEB : self::SOURCE_SYSTEM;
    }

    public function ip(): ?string
    {
        return $this->source() === self::SOURCE_WEB ? $this->request()?->ip() : null;
    }

    public function userAgent(): ?string
    {
        $userAgent = $this->source() === self::SOURCE_WEB ? $this->request()?->userAgent() : null;

        return $userAgent === null ? null : mb_substr($userAgent, 0, 512);
    }

    private function request(): ?Request
    {
        $container = Container::getInstance();

        return $container->bound('request') ? $container->make('request') : null;
    }
}
