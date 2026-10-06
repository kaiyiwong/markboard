<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;

/** PATCH is partial: a field left out is unchanged; null removes `due` or the proof. */
class EditTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'bail', 'required', 'string', new InputRule(InputRules::title(...))],
            'due' => ['sometimes', 'nullable', 'string', new InputRule(InputRules::date(...))],
            'proof' => ['sometimes', 'nullable', 'string', new InputRule(InputRules::proseLine(...))],
            'notes' => ['sometimes', 'array', 'list'],
            'notes.*' => ['bail', 'required', 'string', new InputRule(InputRules::proseLine(...))],
        ];
    }
}
