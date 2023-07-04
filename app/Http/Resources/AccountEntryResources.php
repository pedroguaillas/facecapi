<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountEntryResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'attributes' => [
                'branch_id' => $this->branch_id,
                'category' => $this->category,
                'type' => $this->type,
                'buy' => $this->buy,
                'sale' => $this->sale,
            ]
        ];
    }
}
