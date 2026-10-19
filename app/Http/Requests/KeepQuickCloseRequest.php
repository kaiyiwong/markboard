<?php

namespace App\Http\Requests;

/**
 * Keep a task the triage suggested closing. If-Match is the triage note's etag, as for any write.
 */
class KeepQuickCloseRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{0,63}$/'],
            'task' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/'],
        ];
    }
}
