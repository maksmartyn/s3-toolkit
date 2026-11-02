<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\DTO\Storage\ConnectionTestResultData;
use App\Domain\DTO\Storage\StorageConnectionData;
use App\Exceptions\DomainException;
use Throwable;

/**
 * Проверка подключения: ListBuckets, а если бакеты заданы вручную — HeadBucket по каждому.
 */
final readonly class StorageConnectionTester
{
    private const int SAMPLE_SIZE = 10;

    public function __construct(
        private S3ClientFactory $clients,
        private StorageErrorTranslator $errors,
    ) {}

    public function test(StorageConnectionData $connection): ConnectionTestResultData
    {
        try {
            $client = $this->clients->make($connection);

            if ($connection->buckets !== null && $connection->buckets !== []) {
                foreach ($connection->buckets as $bucket) {
                    try {
                        $client->headBucket(['Bucket' => $bucket]);
                    } catch (Throwable $e) {
                        report_if(config('app.debug'), $e);

                        return $this->failure(__('Bucket :bucket: :error', ['bucket' => $bucket, 'error' => $this->errors->message($e)]));
                    }
                }

                return new ConnectionTestResultData([
                    'ok' => true,
                    'message' => __('Connection successful. All listed buckets are available.'),
                    'buckets' => array_slice($connection->buckets, 0, self::SAMPLE_SIZE),
                    'bucketCount' => count($connection->buckets),
                ]);
            }

            $names = array_map(
                fn (array $bucket): string => (string) $bucket['Name'],
                (array) ($client->listBuckets()->get('Buckets') ?? []),
            );

            return new ConnectionTestResultData([
                'ok' => true,
                'message' => __('Connection successful.'),
                'buckets' => array_slice($names, 0, self::SAMPLE_SIZE),
                'bucketCount' => count($names),
            ]);
        } catch (DomainException $e) {
            report_if(config('app.debug'), $e);

            return $this->failure($e->getMessage());
        } catch (Throwable $e) {
            report_if(config('app.debug'), $e);

            return $this->failure($this->errors->message($e));
        }
    }

    private function failure(string $message): ConnectionTestResultData
    {
        return new ConnectionTestResultData(['ok' => false, 'message' => $message]);
    }
}
