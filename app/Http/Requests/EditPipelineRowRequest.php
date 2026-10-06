<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use App\Markdown\Stage;
use Illuminate\Validation\Rule;

/** PATCH is partial: a field left out is unchanged; a null or empty date empties it. */
class EditPipelineRowRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $cell = ['sometimes', 'bail', 'required', 'string', new InputRule(InputRules::pipelineCell(...))];

        return [
            'company' => $cell,
            'role' => $cell,
            'stage' => ['sometimes', 'required', Rule::enum(Stage::class)],
            'next_action' => $cell,
            'date' => ['sometimes', 'nullable', 'string', InputRule::optionalDate()],
        ];
    }
}
