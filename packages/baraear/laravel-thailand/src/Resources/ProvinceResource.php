<?php

namespace Baraear\ThaiAddress\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
// use Illuminate\Http\Resources\Json\Resource;

class ProvinceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
