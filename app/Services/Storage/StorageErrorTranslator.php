<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Aws\Exception\AwsException;
use GuzzleHttp\Exception\ConnectException;
use Throwable;

/**
 * Ошибки S3 и сети — в понятные пользователю сообщения. Подробности (код ошибки) оставляем, они помогают в поддержке.
 */
final class StorageErrorTranslator
{
    public function message(Throwable $e): string
    {
        if ($e instanceof AwsException) {
            $code = $e->getAwsErrorCode();

            $message = match ($code) {
                'InvalidAccessKeyId' => __('The access key does not exist.'),
                'SignatureDoesNotMatch' => __('The secret key is wrong.'),
                'AccessDenied', 'Forbidden' => __('Access denied. Check the key permissions.'),
                'NoSuchBucket', 'NotFound' => __('The bucket does not exist.'),
                'AuthorizationHeaderMalformed', 'InvalidRegion', 'IllegalLocationConstraintException' => __('Wrong region.'),
                'NotImplemented' => __('The storage does not support this operation.'),
                default => null,
            };

            if ($message === null && $e->getStatusCode() === 403) {
                $message = __('Access denied. Check the key permissions.');
            }

            if ($message === null && $e->getStatusCode() === 404) {
                $message = __('Not found.');
            }

            if ($message === null && $this->isConnectionError($e)) {
                return __('Could not connect to the storage. Check the endpoint.');
            }

            return $code === null ? ($message ?? __('Storage error.')) : ($message ?? __('Storage error.')).' ('.$code.')';
        }

        if ($this->isConnectionError($e)) {
            return __('Could not connect to the storage. Check the endpoint.');
        }

        return __('Storage error.');
    }

    private function isConnectionError(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ConnectException) {
                return true;
            }
        }

        return false;
    }
}
