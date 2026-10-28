<?php

declare(strict_types=1);

namespace App\Services\Bucket;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\Bucket\BucketSectionData;
use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\DTO\Bucket\CorsRuleData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Bucket\VersioningStatus;
use App\Exceptions\DomainException;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGateway;
use App\Services\Storage\StorageGatewayFactory;
use Closure;
use Throwable;

/**
 * Настройки бакета: версионирование, теги, политика, CORS.
 * Чтение каждого раздела возвращает значение или ошибку; запись при ошибке хранилища бросает DomainException.
 */
final readonly class BucketSettingsService
{
    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR;

    public function __construct(
        private StorageGatewayFactory $gateways,
        private StorageRepositoryInterface $storages,
        private StorageErrorTranslator $errors,
        private AuditService $audit,
    ) {}

    public function versioning(int $storageId, string $bucket): BucketSectionData
    {
        return $this->section($storageId, fn (StorageGateway $gateway) => VersioningStatus::fromS3($gateway->getVersioning($bucket)));
    }

    public function setVersioning(int $storageId, string $bucket, VersioningStatus $status): void
    {
        if ($status === VersioningStatus::Unversioned) {
            throw new DomainException(__('Versioning cannot be turned off completely, only suspended.'));
        }

        $this->write($storageId, function (StorageGateway $gateway) use ($storageId, $bucket, $status): void {
            $before = VersioningStatus::fromS3($gateway->getVersioning($bucket));

            if ($before === $status) {
                return;
            }

            $gateway->putVersioning($bucket, $status->toS3());

            $this->record(AuditAction::BucketVersioningChanged, $storageId, $bucket, AuditDetails::changes(
                ['versioning' => $before->label()],
                ['versioning' => $status->label()],
            ));
        });
    }

    public function tags(int $storageId, string $bucket): BucketSectionData
    {
        return $this->section($storageId, fn (StorageGateway $gateway) => array_map(
            fn (array $tag) => new BucketTagData($tag),
            $gateway->getTags($bucket),
        ));
    }

    /**
     * @param  list<BucketTagData>  $tags
     */
    public function setTags(int $storageId, string $bucket, array $tags): void
    {
        $this->write($storageId, function (StorageGateway $gateway) use ($storageId, $bucket, $tags): void {
            $before = $this->tagMap($gateway->getTags($bucket));
            $after = $this->tagMap(array_map(fn (BucketTagData $tag) => ['key' => $tag->key, 'value' => $tag->value], $tags));

            // Удалённые ключи — со значением null; ключи сортируем, чтобы запись в журнале не зависела от порядка
            $after += array_fill_keys(array_keys(array_diff_key($before, $after)), null);
            ksort($after);

            $details = AuditDetails::changes($before, $after);

            if ($details === []) {
                return;
            }

            $gateway->putTags($bucket, array_map(fn (BucketTagData $tag) => ['key' => $tag->key, 'value' => $tag->value], $tags));

            $this->record(AuditAction::BucketTagsChanged, $storageId, $bucket, $details);
        });
    }

    /**
     * Политика в виде отформатированного JSON; data = null — политики нет.
     */
    public function policy(int $storageId, string $bucket): BucketSectionData
    {
        return $this->section($storageId, fn (StorageGateway $gateway) => $this->pretty($gateway->getPolicy($bucket)));
    }

    /**
     * @param  string|null  $policy  JSON; null — удалить политику
     */
    public function setPolicy(int $storageId, string $bucket, ?string $policy): void
    {
        $this->write($storageId, function (StorageGateway $gateway) use ($storageId, $bucket, $policy): void {
            $before = $this->pretty($gateway->getPolicy($bucket));
            $after = $this->pretty($policy);

            if ($this->sameJson($before, $after)) {
                return;
            }

            if ($after === null) {
                $gateway->deletePolicy($bucket);
            } else {
                $gateway->putPolicy($bucket, (string) json_encode(json_decode($after), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            $this->record(AuditAction::BucketPolicyChanged, $storageId, $bucket, AuditDetails::document('policy', $before, $after));
        });
    }

    public function cors(int $storageId, string $bucket): BucketSectionData
    {
        return $this->section($storageId, fn (StorageGateway $gateway) => array_map($this->corsRule(...), $gateway->getCors($bucket)));
    }

    /**
     * @param  list<CorsRuleData>  $rules  пустой список — удалить CORS
     */
    public function setCors(int $storageId, string $bucket, array $rules): void
    {
        $this->write($storageId, function (StorageGateway $gateway) use ($storageId, $bucket, $rules): void {
            $before = $gateway->getCors($bucket);
            $after = array_map($this->corsToS3(...), $rules);

            $beforeJson = $before === [] ? null : json_encode(array_map($this->corsToS3(...), array_map($this->corsRule(...), $before)), self::JSON_FLAGS);
            $afterJson = $after === [] ? null : json_encode($after, self::JSON_FLAGS);

            if ($beforeJson === $afterJson) {
                return;
            }

            $gateway->putCors($bucket, $after);

            $this->record(AuditAction::BucketCorsChanged, $storageId, $bucket, AuditDetails::document('cors', $beforeJson, $afterJson));
        });
    }

    /**
     * @param  array<string, mixed>  $rule  правило в формате S3 API
     */
    private function corsRule(array $rule): CorsRuleData
    {
        return new CorsRuleData([
            'id' => isset($rule['ID']) ? (string) $rule['ID'] : null,
            'allowedOrigins' => array_values(array_map(strval(...), (array) ($rule['AllowedOrigins'] ?? []))),
            'allowedMethods' => array_values(array_map(strval(...), (array) ($rule['AllowedMethods'] ?? []))),
            'allowedHeaders' => array_values(array_map(strval(...), (array) ($rule['AllowedHeaders'] ?? []))),
            'exposeHeaders' => array_values(array_map(strval(...), (array) ($rule['ExposeHeaders'] ?? []))),
            'maxAgeSeconds' => isset($rule['MaxAgeSeconds']) ? (int) $rule['MaxAgeSeconds'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function corsToS3(CorsRuleData $rule): array
    {
        return array_filter([
            'ID' => $rule->id,
            'AllowedOrigins' => $rule->allowedOrigins,
            'AllowedMethods' => $rule->allowedMethods,
            'AllowedHeaders' => $rule->allowedHeaders === [] ? null : $rule->allowedHeaders,
            'ExposeHeaders' => $rule->exposeHeaders === [] ? null : $rule->exposeHeaders,
            'MaxAgeSeconds' => $rule->maxAgeSeconds,
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<array{key: string, value: string}>  $tags
     * @return array<string, string>
     */
    private function tagMap(array $tags): array
    {
        $map = [];

        foreach ($tags as $tag) {
            $map['tag:'.$tag['key']] = $tag['value'];
        }

        ksort($map);

        return $map;
    }

    private function pretty(?string $json): ?string
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        return json_encode(json_decode($json, false, 512, JSON_THROW_ON_ERROR), self::JSON_FLAGS);
    }

    private function sameJson(?string $a, ?string $b): bool
    {
        return ($a === null ? null : json_decode($a, true)) == ($b === null ? null : json_decode($b, true));
    }

    /**
     * @param  Closure(StorageGateway): mixed  $read
     */
    private function section(int $storageId, Closure $read): BucketSectionData
    {
        try {
            return new BucketSectionData(['data' => $read($this->gateways->forStorage($storageId))]);
        } catch (DomainException $e) {
            return new BucketSectionData(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return new BucketSectionData(['error' => $this->errors->message($e)]);
        }
    }

    /**
     * @param  Closure(StorageGateway): void  $write
     */
    private function write(int $storageId, Closure $write): void
    {
        $gateway = $this->gateways->forStorage($storageId);

        try {
            $write($gateway);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException(__('The storage rejected the change: :error', ['error' => $this->errors->message($e)]), previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(AuditAction $action, int $storageId, string $bucket, array $details): void
    {
        $storage = $this->storages->findOrFail($storageId);

        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $storageId,
            'subjectLabel' => $bucket,
            'details' => [...AuditDetails::storage($storageId, $storage->name), ...$details],
        ]));
    }
}
