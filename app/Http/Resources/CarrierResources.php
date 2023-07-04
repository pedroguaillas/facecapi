<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CarrierResources extends JsonResource
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
            'atts' => [
                'identication' => $this->identication,
                'name' => $this->name,
                'address' => $this->address,
                'license_plate' => $this->license_plate,
            ]
        ];
    }
}
