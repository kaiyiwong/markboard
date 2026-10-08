<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use App\Markdown\Section;
use Illuminate\Validation\Rule;

/** Moving to Done, or into Waiting on without `waiting`, is a state check the editor makes (422). */
class MoveTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'section' => ['required', Rule::enum(Section::class)],
            'waiting' => ['nullable', 'string', new InputRule(InputRules::metadataValue(...))],
        ];
    }
}
