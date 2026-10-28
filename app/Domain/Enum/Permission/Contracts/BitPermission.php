<?php

declare(strict_types=1);

namespace App\Domain\Enum\Permission\Contracts;

/**
 * Право, хранящееся одним битом в маске.
 */
interface BitPermission extends \BackedEnum
{
    /**
     * Номер бита, 0..62 (BIGINT UNSIGNED, но значения держим в пределах PHP int).
     */
    public function bit(): int;

    public function label(): string;
}
