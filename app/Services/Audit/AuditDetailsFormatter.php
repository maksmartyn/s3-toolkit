<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Domain\Enum\Permission\PermissionScope;

/**
 * Подробности записи журнала в читаемом виде на текущем языке.
 */
final class AuditDetailsFormatter
{
    /**
     * @param  array<string, mixed>|null  $details
     * @return list<string>
     */
    public function lines(?array $details): array
    {
        if ($details === null) {
            return [];
        }

        $lines = [];

        if (is_array($details['user'] ?? null)) {
            $lines[] = $this->t('User: :name (:email)', [
                'name' => (string) ($details['user']['name'] ?? ''),
                'email' => (string) ($details['user']['email'] ?? ''),
            ]);
        }

        if (is_array($details['storage'] ?? null)) {
            $lines[] = $this->t('Storage: :name', ['name' => (string) ($details['storage']['name'] ?? '')]);
        }

        foreach ((array) ($details['changes'] ?? []) as $field => $change) {
            $lines[] = __(':field: :from → :to', [
                'field' => $this->fieldLabel((string) $field),
                'from' => $this->value($change['from'] ?? null),
                'to' => $this->value($change['to'] ?? null),
            ]);
        }

        if (is_array($details['permissions'] ?? null)) {
            array_push($lines, ...$this->permissionLines($details['permissions']));
        }

        foreach ((array) ($details['documents'] ?? []) as $name => $document) {
            $lines[] = $this->t(match (true) {
                ($document['from'] ?? null) === null => ':name: set',
                ($document['to'] ?? null) === null => ':name: removed',
                default => ':name: changed',
            }, ['name' => $this->fieldLabel((string) $name)]);
        }

        foreach ((array) ($details['values'] ?? []) as $key => $value) {
            $lines[] = $this->t(':field: :value', ['field' => $this->fieldLabel((string) $key), 'value' => $this->value($value)]);
        }

        foreach ((array) ($details['flags'] ?? []) as $flag => $value) {
            if ($value === true) {
                $lines[] = $this->flagLabel((string) $flag);
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return list<string>
     */
    private function permissionLines(array $permissions): array
    {
        $scope = PermissionScope::tryFrom((string) ($permissions['scope'] ?? ''));
        $lines = [];

        foreach (['granted' => 'Granted: :list', 'revoked' => 'Revoked: :list'] as $key => $template) {
            $names = (array) ($permissions[$key] ?? []);

            if ($names !== []) {
                $labels = array_map(fn (mixed $name): string => $this->permissionLabel($scope, (string) $name), $names);
                $lines[] = $this->t($template, ['list' => implode(', ', $labels)]);
            }
        }

        return $lines === [] ? [$this->t('No changes')] : $lines;
    }

    private function permissionLabel(?PermissionScope $scope, string $name): string
    {
        if ($scope !== null) {
            foreach ($scope->enum()::cases() as $permission) {
                if ($permission->name === $name) {
                    return $permission->label();
                }
            }
        }

        // Право удалено из кода, но в журнале осталось
        return $name;
    }

    private function fieldLabel(string $field): string
    {
        if (str_starts_with($field, 'tag:')) {
            return $this->t('Tag :key', ['key' => substr($field, 4)]);
        }

        return match ($field) {
            'name' => __('Name'),
            'email' => __('Email'),
            'provider' => __('Provider'),
            'endpoint' => __('Endpoint'),
            'region' => __('Region'),
            'use_path_style' => __('Path-style addressing'),
            'verify_ssl' => __('Verify SSL certificate'),
            'buckets' => __('Buckets'),
            'capabilities' => __('Supported features'),
            'deleted_objects' => __('Deleted objects and versions'),
            'versioning' => __('Versioning'),
            'policy' => __('Bucket policy'),
            'cors' => __('CORS'),
            default => $field,
        };
    }

    private function flagLabel(string $flag): string
    {
        return match ($flag) {
            'superadmin' => __('Superadmin'),
            'temporaryPassword' => __('Temporary password issued'),
            'credentialsChanged' => __('Access keys replaced'),
            'objectLock' => __('Object Lock enabled'),
            default => $flag,
        };
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function t(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }

    private function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_scalar($value) => (string) $value,
            is_array($value) && array_is_list($value) => implode(', ', array_map(strval(...), $value)),
            default => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
        };
    }
}
