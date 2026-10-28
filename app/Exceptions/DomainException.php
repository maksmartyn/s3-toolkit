<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Нарушение бизнес-правила. Сообщение показывается пользователю, поэтому должно быть переведено.
 *
 * В вебе превращается в редирект назад с toast-ошибкой, в JSON — в ответ 422.
 */
class DomainException extends RuntimeException {}
