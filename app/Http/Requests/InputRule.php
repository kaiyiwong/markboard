<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One of the InputRules checks as a validation rule. It is implicit, so it also runs on an empty
 * string, which Laravel would otherwise let through unchecked: "" is refused as empty, not
 * mistaken for null (which removes a proof or a due date).
 */
final class InputRule implements ValidationRule
{
    public bool $implicit = true;

    /**
     * @param  Closure(string): (string|null)  $check  returns the problem, or null when the value is fine
     */
    public function __construct(
        private readonly Closure $check,
    ) {}

    /** A pipeline date may be empty. */
    public static function optionalDate(): self
    {
        return new self(fn (string $value): ?string => $value === '' ? null : InputRules::date($value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ($message = ($this->check)($value)) !== null) {
            $fail($message);
        }
    }
}
