<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;

/** `waiting` is needed only when undoing into Waiting on a task that has none (a state check, 422). */
class UndoTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'waiting' => ['nullable', 'string', new InputRule(InputRules::metadataValue(...))],
        ];
    }
}
