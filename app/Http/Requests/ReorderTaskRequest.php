<?php

namespace App\Http\Requests;

/** A position outside the section is a state check the editor makes (422). */
class ReorderTaskRequest extends FileEditRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'position' => ['required', 'integer'],
        ];
    }
}
