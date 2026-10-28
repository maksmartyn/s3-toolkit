<?php

declare(strict_types=1);

namespace App\Domain\Enum\Bucket;

/**
 * Готовые наборы ACL. Значения — как в S3 API.
 */
enum CannedAcl: string
{
    case Private = 'private';
    case PublicRead = 'public-read';
    case PublicReadWrite = 'public-read-write';
    case AuthenticatedRead = 'authenticated-read';

    public function label(): string
    {
        return match ($this) {
            self::Private => __('Private'),
            self::PublicRead => __('Public read'),
            self::PublicReadWrite => __('Public read and write'),
            self::AuthenticatedRead => __('Read for any authenticated user'),
        };
    }

    /**
     * Какому готовому набору соответствуют гранты. null — гранты настроены вручную и в наборы не укладываются.
     *
     * @param  list<array{grantee: string, type: string, permission: string}>  $grants
     */
    public static function detect(array $grants): ?self
    {
        $group = fn (string $suffix, string $permission): bool => array_any(
            $grants,
            fn (array $grant) => str_ends_with($grant['grantee'], $suffix) && $grant['permission'] === $permission,
        );
        $nonOwner = array_filter($grants, fn (array $grant) => $grant['type'] === 'Group');

        return match (true) {
            $group('/AllUsers', 'READ') && $group('/AllUsers', 'WRITE') && count($nonOwner) === 2 => self::PublicReadWrite,
            $group('/AllUsers', 'READ') && count($nonOwner) === 1 => self::PublicRead,
            $group('/AuthenticatedUsers', 'READ') && count($nonOwner) === 1 => self::AuthenticatedRead,
            $nonOwner === [] => self::Private,
            default => null,
        };
    }
}
