<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Support;

use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Запуск клиента MinIO (mc) для Admin API. Подключение передаётся через MC_HOST_*, конфигурация — во временном каталоге.
 */
final readonly class McRunner
{
    private const string ALIAS = 's3t';

    public function __construct(
        private ProcessFactory $processes,
        private string $binary,
        private int $timeout,
    ) {}

    /**
     * @param  list<string>  $args  аргументы после «mc»; псевдоним хоста — {alias}
     * @return list<array<string, mixed>> строки JSON-вывода
     */
    public function run(string $endpoint, string $accessKey, string $secretKey, bool $verifySsl, array $args): array
    {
        $parts = parse_url($endpoint);
        $host = sprintf(
            '%s://%s:%s@%s%s',
            $parts['scheme'] ?? 'https',
            rawurlencode($accessKey),
            rawurlencode($secretKey),
            $parts['host'] ?? '',
            isset($parts['port']) ? ':'.$parts['port'] : '',
        );

        $configDir = sys_get_temp_dir().'/mc-'.Str::random(12);

        $command = [
            $this->binary,
            ...array_map(fn (string $arg) => $arg === '{alias}' ? self::ALIAS : $arg, $args),
            '--json',
            '--no-color',
            '--config-dir', $configDir,
        ];

        if (! $verifySsl) {
            $command[] = '--insecure';
        }

        try {
            $result = $this->processes
                ->newPendingProcess()
                ->timeout($this->timeout)
                ->env(['MC_HOST_'.self::ALIAS => $host, 'HOME' => $configDir])
                ->run($command);
        } finally {
            File::deleteDirectory($configDir);
        }

        $lines = [];

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $lines[] = $decoded;
            }
        }

        foreach ($lines as $line) {
            if (($line['status'] ?? null) === 'error') {
                $error = (array) ($line['error'] ?? []);
                $cause = (array) ($error['cause'] ?? []);

                throw new RuntimeException(trim(($error['message'] ?? 'mc error').' '.($cause['message'] ?? '')));
            }
        }

        if (! $result->successful() && $lines === []) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'mc exited with code '.$result->exitCode());
        }

        return $lines;
    }
}
