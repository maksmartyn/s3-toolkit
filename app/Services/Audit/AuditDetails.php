<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\ValueObject\Permission\PermissionMask;

/**
 * Построение подробностей записи журнала в едином формате, который понимает AuditDetailsFormatter.
 *
 *  - changes:     {поле: {from, to}} — только изменившиеся поля;
 *  - permissions: {scope, granted: [имена enum], revoked: [имена enum]} — имена стабильнее подписей;
 *  - flags:       {ключ: bool}, например superadmin.
 */
final class AuditDetails
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{changes?: array<string, array{from: mixed, to: mixed}>}
     */
    public static function changes(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $to) {
            $from = $before[$field] ?? null;

            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes === [] ? [] : ['changes' => $changes];
    }

    /**
     * @template T of BitPermission
     *
     * @param  PermissionMask<T>  $before
     * @param  PermissionMask<T>  $after
     * @return array{permissions: array{scope: string, granted: list<string>, revoked: list<string>}}
     */
    public static function permissions(PermissionScope $scope, PermissionMask $before, PermissionMask $after): array
    {
        $names = fn (array $permissions): array => array_values(array_map(fn (BitPermission $p): string => $p->name, $permissions));

        $beforeList = $before->permissions();
        $afterList = $after->permissions();

        return ['permissions' => [
            'scope' => $scope->value,
            'granted' => $names(array_values(array_udiff($afterList, $beforeList, self::compare(...)))),
            'revoked' => $names(array_values(array_udiff($beforeList, $afterList, self::compare(...)))),
        ]];
    }

    /**
     * Пользователь, к которому относится действие над другим объектом (например, права на хранилище).
     *
     * @return array{user: array{id: int, name: string, email: string}}
     */
    public static function user(int $id, string $name, string $email): array
    {
        return ['user' => ['id' => $id, 'name' => $name, 'email' => $email]];
    }

    /**
     * Хранилище, в котором находится объект действия (бакет).
     *
     * @return array{storage: array{id: int, name: string}}
     */
    public static function storage(int $id, string $name): array
    {
        return ['storage' => ['id' => $id, 'name' => $name]];
    }

    /**
     * Документ целиком «было/стало» (политика, CORS): секретов в них нет, а для аудита важно видеть права доступа.
     * null — документа не было (или он удалён).
     *
     * @return array{documents: array<string, array{from: string|null, to: string|null}>}
     */
    public static function document(string $name, ?string $from, ?string $to): array
    {
        return ['documents' => [$name => ['from' => $from, 'to' => $to]]];
    }

    /**
     * Произвольные значения для показа: {ключ: число|строка}.
     *
     * @param  array<string, int|string>  $values
     * @return array{values: array<string, int|string>}
     */
    public static function values(array $values): array
    {
        return ['values' => $values];
    }

    /**
     * @param  array<string, bool>  $flags
     * @return array{flags: array<string, bool>}
     */
    public static function flags(array $flags): array
    {
        return ['flags' => $flags];
    }

    private static function compare(BitPermission $a, BitPermission $b): int
    {
        return $a->bit() <=> $b->bit();
    }
}
