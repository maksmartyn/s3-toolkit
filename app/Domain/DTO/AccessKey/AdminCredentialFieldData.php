<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Поле формы учётных данных для управления ключами. Значения секретных полей наружу не отдаются.
 */
final class AdminCredentialFieldData extends DTO
{
    public string $name;

    public string $label;

    public bool $secret = false;

    public bool $multiline = false;

    public bool $required = false;

    public ?string $hint = null;

    public ?string $placeholder = null;

    /** Текущее значение несекретного поля */
    public ?string $value = null;

    /** Секретное поле уже заполнено */
    public bool $isSet = false;
}
