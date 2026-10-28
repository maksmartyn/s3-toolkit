<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\DTO\Operation\OperationData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Изменение фоновой операции. Отправляется сразу (не через очередь): обычно его и так шлёт задача из очереди.
 */
final readonly class OperationUpdated implements ShouldBroadcastNow
{
    public function __construct(public OperationData $operation) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('storages.'.$this->operation->storageId);
    }

    public function broadcastAs(): string
    {
        return 'operation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['operation' => $this->operation->toArray()];
    }
}
