<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Skip tonight (create orders/STOP) or not (delete it). */
class NightStopRequest extends FormRequest
{
    /** Markboard runs on localhost only, with no auth. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stop' => ['required', 'boolean'],
        ];
    }
}
