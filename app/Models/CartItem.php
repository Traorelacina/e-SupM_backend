<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'size',
        'color',
        'options',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'price'      => 'float',
        'options'    => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['line_total'];

    // ── Relations ────────────────────────────────────────────────────────────

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Accesseurs ───────────────────────────────────────────────────────────

    public function getLineTotalAttribute(): float
    {
        return round((float) $this->price * (int) $this->quantity, 2);
    }
}