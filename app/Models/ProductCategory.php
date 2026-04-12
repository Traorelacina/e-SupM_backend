<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'name_en',
        'slug',
        'description',
        'image',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    // ==================== RELATIONSHIPS ====================

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class, 'product_category_id')
                    ->where('is_active', true);
    }

    // ==================== SCOPES ====================

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForCategory($q, int $categoryId)
    {
        return $q->where('category_id', $categoryId);
    }

    // ==================== ACCESSORS ====================

    public function getImageUrlAttribute(): ?string
    {
        $attrs = $this->getAttributes();

        // La colonne image n'est pas toujours chargée (select partiel)
        if (!array_key_exists('image', $attrs) || !$attrs['image']) {
            return null;
        }

        if (str_starts_with($attrs['image'], 'http')) {
            return $attrs['image'];
        }

        return asset('storage/' . $attrs['image']);
    }
}