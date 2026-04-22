<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBoxItem extends Model
{
    protected $fillable = [
        'food_box_id',
        'product_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'sort_order' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function foodBox(): BelongsTo
    {
        return $this->belongsTo(FoodBox::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)
            ->with(['primaryImage', 'category:id,name']);
    }
}