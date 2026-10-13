<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merge or Discard on a night branch. Git state isn't one file, so instead of If-Match the request
 * names the branch head the page showed [D35].
 */
class NightBranchRequest extends FormRequest
{
    /** Markboard runs on localhost only, with no auth. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'head' => ['required', 'string', 'regex:/^[0-9a-fA-F]{7,40}$/'],
        ];
    }
}
