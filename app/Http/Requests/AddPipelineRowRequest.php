<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use App\Markdown\Stage;
use Illuminate\Validation\Rule;

/** All five fields; the date may be empty or left out. */
class AddPipelineRowRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $cell = ['bail', 'required', 'string', new InputRule(InputRules::pipelineCell(...))];

        return [
            'company' => $cell,
            'role' => $cell,
            'stage' => ['required', Rule::enum(Stage::class)],
            'next_action' => $cell,
            'date' => ['nullable', 'string', InputRule::optionalDate()],
        ];
    }
}
