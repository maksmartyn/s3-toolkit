<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Базовая проверка структуры политики бакета. Окончательно её проверяет хранилище;
 * здесь ловим типичные ошибки, чтобы показать их у поля, а не текстом из ответа S3.
 */
final class BucketPolicyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The policy must be a JSON document.'));

            return;
        }

        $policy = json_decode($value, true);

        if (! is_array($policy) || array_is_list($policy)) {
            $fail(__('The policy must be a JSON object.'));

            return;
        }

        if (($policy['Version'] ?? null) !== '2012-10-17') {
            $fail(__('The policy must have "Version": "2012-10-17".'));

            return;
        }

        $statements = $policy['Statement'] ?? null;

        if (is_array($statements) && ! array_is_list($statements)) {
            $statements = [$statements];
        }

        if (! is_array($statements) || $statements === []) {
            $fail(__('The policy must have at least one statement.'));

            return;
        }

        foreach ($statements as $index => $statement) {
            $number = (int) $index + 1;

            if (! is_array($statement)) {
                $fail(__('Statement :number must be an object.', ['number' => $number]));

                return;
            }

            if (! in_array($statement['Effect'] ?? null, ['Allow', 'Deny'], true)) {
                $fail(__('Statement :number: Effect must be Allow or Deny.', ['number' => $number]));

                return;
            }

            if (! isset($statement['Action']) && ! isset($statement['NotAction'])) {
                $fail(__('Statement :number: Action is required.', ['number' => $number]));

                return;
            }

            if (! isset($statement['Resource']) && ! isset($statement['NotResource'])) {
                $fail(__('Statement :number: Resource is required.', ['number' => $number]));

                return;
            }
        }
    }
}
