<?php

namespace App\Http\Requests;

/**
 * Kai's answers to a work order's questions. If-Match is the order file's etag, as for any write.
 */
class AnswerOrderRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'string', 'max:20000'],
        ];
    }
}
