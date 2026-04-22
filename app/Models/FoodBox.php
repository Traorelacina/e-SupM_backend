<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoodBox extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tagline',
        'image',
        'price',
        'compare_price',
        'frequency',
        'is_active',
        'is_featured',
        'max_subscribers',
        'subscribers_count',
        'sort_order',
        'badge_label',
        'badge_color',
    ];

    protected $casts = [
        'price'             => 'float',
        'compare_price'     => 'float',
        'is_active'         => 'boolean',
        'is_featured'       => 'boolean',
        'max_subscribers'   => 'integer',
        'subscribers_count' => 'integer',
        'sort_order'        => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(FoodBoxItem::class)->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'food_box_items')
            ->withPivot('quantity', 'sort_order')
            ->orderBy('food_box_items.sort_order');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->compare_price && $this->compare_price > $this->price) {
            return (int) round((1 - $this->price / $this->compare_price) * 100);
        }
        return null;
    }

    public function getIsFullAttribute(): bool
    {
        if ($this->max_subscribers === null) return false;
        return $this->subscribers_count >= $this->max_subscribers;
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'weekly'   => 'Chaque semaine',
            'biweekly' => 'Toutes les 2 semaines',
            'monthly'  => 'Chaque mois',
            default    => $this->frequency,
        };
    }
}