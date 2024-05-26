<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResources extends JsonResource
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
                'code' => $this->code,
                'type_product' => $this->type_product,
                'name' => $this->name,
                'price1' => $this->price1,
                'iva' => $this->percentage,
                'ice' => $this->ice,
                'irbpnr' => $this->irbpnr,
                'stock' => $this->stock,
                'tourism' => $this->tourism,
            ],
            'iva' => [
                'code' => $this->iva_code,
                'percentage' => $this->percentage,
            ]
            // 'category' => [
            //     'category_id' => $this->category_id,
            //     'category' => $this->category,
            // ],
            // 'unity' => [
            //     'unity_id' => $this->unity_id,
            //     'unity' => $this->unity
            // ]
        ];
    }
}
