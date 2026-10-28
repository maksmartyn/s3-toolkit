<?php

use App\Domain\DTO\Storage\StorageConnectionData;
use App\Domain\Enum\Storage\StorageProvider;
use App\Services\Storage\S3ClientFactory;
use App\Services\Storage\StorageConnectionTester;

/*
 * Проверки на настоящем MinIO. Запуск:
 *   docker compose --profile s3 up -d minio
 *   docker compose run --rm tools vendor/bin/pest tests/Integration
 * Без MinIO тесты пропускаются.
 */

function minioConnection(array $overrides = []): StorageConnectionData
{
    return new StorageConnectionData([
        'provider' => StorageProvider::Minio,
        'endpoint' => env('MINIO_TEST_ENDPOINT', 'http://minio:9000'),
        'region' => 'us-east-1',
        'usePathStyle' => true,
        'verifySsl' => false,
        'accessKeyId' => env('MINIO_ROOT_USER', 'minioadmin'),
        'secretAccessKey' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
        ...$overrides,
    ]);
}

beforeEach(function () {
    $parts = parse_url((string) minioConnection()->endpoint);
    $socket = @fsockopen((string) $parts['host'], (int) ($parts['port'] ?? 80), $errno, $error, 1);

    if ($socket === false) {
        $this->markTestSkipped('MinIO is not running.');
    }

    fclose($socket);

    $this->bucket = 'it-'.bin2hex(random_bytes(4));
    $this->client = app(S3ClientFactory::class)->make(minioConnection());
    $this->client->createBucket(['Bucket' => $this->bucket]);
});

afterEach(function () {
    if (isset($this->bucket)) {
        $this->client->deleteBucket(['Bucket' => $this->bucket]);
    }
});

it('lists buckets on a real MinIO', function () {
    $result = app(StorageConnectionTester::class)->test(minioConnection());

    expect($result->ok)->toBeTrue()
        ->and($result->bucketCount)->toBeGreaterThanOrEqual(1);
});

it('checks listed buckets on a real MinIO', function () {
    $tester = app(StorageConnectionTester::class);

    expect($tester->test(minioConnection(['buckets' => [$this->bucket]]))->ok)->toBeTrue()
        ->and($tester->test(minioConnection(['buckets' => ['missing-'.$this->bucket]])))
        ->ok->toBeFalse()
        ->message->toContain('missing-');
});

it('reports a wrong secret on a real MinIO', function () {
    $result = app(StorageConnectionTester::class)->test(minioConnection(['secretAccessKey' => 'wrong-secret']));

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('SignatureDoesNotMatch');
});

it('reports an unreachable endpoint', function () {
    $result = app(StorageConnectionTester::class)->test(minioConnection(['endpoint' => 'http://minio:1']));

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('Не удалось подключиться к хранилищу. Проверьте endpoint.');
});
