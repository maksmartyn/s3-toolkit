<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\DomainException;

/**
 * Проверка endpoint перед обращением к нему: только http(s), и при необходимости — не во внутренней сети.
 */
final readonly class EndpointGuard
{
    public function __construct(private bool $allowPrivate) {}

    public function assertAllowed(?string $endpoint): void
    {
        if ($endpoint === null) {
            return;
        }

        $parts = parse_url($endpoint);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            throw new DomainException(__('The endpoint must be an http or https URL.'));
        }

        if (! $this->allowPrivate && $this->isPrivate($host)) {
            throw new DomainException(__('Endpoints in internal networks are not allowed.'));
        }
    }

    private function isPrivate(string $host): bool
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            // Не резолвится — подключение всё равно не удастся
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }
}
