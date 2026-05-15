<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'urn' => $this->urn,
            'name' => $this->name,
            'type' => $this->type,
            'phase' => $this->phase === 'Not applicable' ? null : $this->phase,
            'distance_metres' => $this->distance_metres ? (int) round($this->distance_metres) : null,
        ];
    }
}
