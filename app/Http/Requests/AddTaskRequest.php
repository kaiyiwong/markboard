<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;

class AddTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', new InputRule(InputRules::title(...))],
            'due' => ['nullable', 'string', new InputRule(InputRules::date(...))],
            'proof' => ['nullable', 'string', new InputRule(InputRules::proseLine(...))],
        ];
    }
}
