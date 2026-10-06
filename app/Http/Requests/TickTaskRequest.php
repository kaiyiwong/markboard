<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;

class TickTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'evidence' => ['nullable', 'string', new InputRule(InputRules::metadataValue(...))],
        ];
    }
}
