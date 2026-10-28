<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Support;

use Aws\Iam\IamClient;

/**
 * Клиент AWS IAM. Отдельный класс — чтобы в тестах подменять ответы.
 */
class IamClientFactory
{
    public function make(string $accessKeyId, string $secretAccessKey): IamClient
    {
        return new IamClient($this->config($accessKeyId, $secretAccessKey));
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(string $accessKeyId, string $secretAccessKey): array
    {
        return [
            'version' => 'latest',
            // IAM глобальный, его endpoint — в us-east-1
            'region' => 'us-east-1',
            'credentials' => ['key' => $accessKeyId, 'secret' => $secretAccessKey],
            'http' => ['connect_timeout' => 5, 'timeout' => 20],
        ];
    }
}
