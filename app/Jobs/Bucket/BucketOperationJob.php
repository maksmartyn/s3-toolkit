<?php

declare(strict_types=1);

namespace App\Jobs\Bucket;

use App\Services\Bucket\Contracts\OperationRunner;
use App\Services\Operation\OperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

/**
 * Один шаг долгой операции. Если за отведённое время работа не закончена,
 * задача ставит в очередь своё продолжение: так не упираемся в таймаут очереди и лимит памяти воркера.
 */
abstract class BucketOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Ошибки обрабатывает исполнитель (операция помечается неудачной), повторять шаг не нужно */
    public int $tries = 1;

    /** Из s3toolkit.operations.job_timeout: шаг плюс худший случай запроса к хранилищу */
    public int $timeout;

    public function __construct(public readonly int $operationId)
    {
        $this->timeout = (int) config('s3toolkit.operations.job_timeout', 150);
    }

    /**
     * @return class-string<OperationRunner>
     */
    abstract protected function runner(): string;

    public function handle(): void
    {
        /** @var OperationRunner $runner */
        $runner = app($this->runner());

        $finished = $runner->run($this->operationId, (float) config('s3toolkit.operations.step_seconds', 20));

        if (! $finished) {
            static::dispatch($this->operationId);
        }
    }

    /**
     * Шаг убит по таймауту (хранилище отвечает очень медленно) или упал вне исполнителя.
     * Прогресс сохраняется после каждой страницы, поэтому после таймаута операцию можно продолжить с того же места.
     */
    public function failed(?Throwable $e): void
    {
        if (app(OperationService::class)->stepFailed($this->operationId, $e instanceof TimeoutExceededException)) {
            static::dispatch($this->operationId);
        }
    }
}
