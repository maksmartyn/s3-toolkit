<?php

declare(strict_types=1);

namespace App\Jobs\Bucket;

use App\Services\Bucket\Contracts\OperationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Один шаг долгой операции. Если за отведённое время работа не закончена,
 * задача ставит в очередь своё продолжение: так не упираемся в таймаут очереди и лимит памяти воркера.
 */
abstract class BucketOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Ошибки обрабатывает исполнитель (операция помечается неудачной), повторять шаг не нужно */
    public int $tries = 1;

    /** Шаг длится ~20 секунд; таймаут должен быть меньше retry_after очереди (90), иначе задачу запустят повторно параллельно */
    public int $timeout = 60;

    public function __construct(public readonly int $operationId) {}

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
}
