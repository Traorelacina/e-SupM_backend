<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'discount_percentage' => $this->discount_percentage,
            'in_stock' => $this->in_stock,
            'stock_quantity' => $this->stock_quantity,
            'is_bio' => $this->is_bio,
            'is_local' => $this->is_local,
            'is_vegan' => $this->is_vegan,
            'is_gluten_free' => $this->is_gluten_free,
            'is_premium' => $this->is_premium,
            'is_active' => $this->is_active,
            'sales_count' => $this->sales_count,
            'views_count' => $this->views_count,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'brand' => $this->brand,
            'weight' => $this->weight,
            'unit' => $this->unit,
            'origin' => $this->origin,
            'primary_image_url' => $this->primary_image_url,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'partner' => $this->whenLoaded('partner'),
            'size_options' => SizeOptionResource::collection($this->whenLoaded('sizeOptions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}