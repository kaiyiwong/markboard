<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every edit but Discard names the version of the file the user saw, as If-Match: without it the
 * request is refused (428) before anything else is checked. Text is trimmed the way the checker
 * trims (Python's str.strip()), so what is validated is exactly what is written.
 *
 * Used as is by the edits that take no fields (Cancel, Apply); the others extend it.
 */
class FileEditRequest extends FormRequest
{
    /** Markboard runs on localhost only, with no auth. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** The hash from If-Match: "<hash>", without its quotes or a weak W/ prefix. */
    public function etag(): string
    {
        return trim((string) preg_replace('#^W/#', '', (string) $this->header('If-Match')), '"');
    }

    protected function prepareForValidation(): void
    {
        abort_if(blank($this->header('If-Match')), 428, 'Send the etag of the file version you saw as If-Match.');
        $this->replace(self::trimmed($this->all()));
    }

    private static function trimmed(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => InputRules::trim($value),
            is_array($value) => array_map(self::trimmed(...), $value),
            default => $value,
        };
    }
}
