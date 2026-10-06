<?php

namespace App\Http\Resources;

use App\Models\PipelineRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pipeline row, addressed by its position among the table's rows (1-based).
 *
 * @mixin PipelineRow
 */
class PipelineRowResource extends JsonResource
{
    /**
     * @return array{position: int, company: string, role: string, stage: string|null, next_action: string, date: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'company' => $this->company,
            'role' => $this->role,
            'stage' => $this->stage?->value,
            'next_action' => $this->next_action,
            'date' => $this->date?->toDateString(),
        ];
    }
}
