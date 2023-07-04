<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResources extends JsonResource
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
                'date' => $this->date,
                'voucher_type' => $this->voucher_type,
                'serie' => $this->serie,
                'state' => $this->state,
                'total' => $this->total,
                'xml' => $this->xml,
                'send_mail' => $this->send_mail,
                'extra_detail' => $this->extra_detail
            ],
            'customer' => [
                'name' => $this->name,
                'email' => $this->email,
            ]
        ];
    }
}
