<?php

namespace App\Http\Requests;

use App\Markdown\InputRules;
use Illuminate\Validation\Rule;

/** Kai's answer to a task waiting on him. Whether `value` is on the card is checked against the card. */
class AnswerTaskRequest extends FileEditRequest
{
    public const array ANSWERS = ['pick', 'choose', 'approve', 'redo', 'reply', 'done', 'drop'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answer' => ['required', Rule::in(self::ANSWERS)],
            'value' => ['required_if:answer,pick,choose', 'nullable', 'string'],
            'note' => ['required_if:answer,reply', 'nullable', 'string', new InputRule(InputRules::proseLine(...))],
        ];
    }
}
