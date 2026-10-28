<?php

declare(strict_types=1);

namespace App\Services\Bucket\Contracts;

interface OperationRunner
{
    /**
     * Выполняет операцию, пока не кончится время шага.
     *
     * @return bool true — операция завершена (успешно или с ошибкой), false — нужен следующий шаг
     */
    public function run(int $operationId, float $budgetSeconds): bool;
}
