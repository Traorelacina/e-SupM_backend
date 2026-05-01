<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conseil extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'category', 'content_type',
        'body', 'video_url', 'video_provider', 'video_duration',
        'thumbnail', 'gallery', 'tags', 'reading_time', 'views', 'likes',
        'recipe_ingredients', 'recipe_prep_time', 'recipe_cook_time',
        'recipe_servings', 'recipe_difficulty',
        'is_published', 'is_featured', 'published_at', 'author_id',
    ];

    protected $casts = [
        'gallery'             => 'array',
        'recipe_ingredients'  => 'array',
        'is_published'        => 'boolean',
        'is_featured'         => 'boolean',
        'published_at'        => 'datetime',
        'views'               => 'integer',
        'likes'               => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Accesseurs ────────────────────────────────────────────────
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }
        return str_starts_with($this->thumbnail, 'http')
            ? $this->thumbnail
            : asset('storage/' . $this->thumbnail);
    }

    public function getTagsArrayAttribute(): array
    {
        if (! $this->tags) return [];
        return array_map('trim', explode(',', $this->tags));
    }

    public function getTotalTimeAttribute(): ?int
    {
        if ($this->category !== 'recette') return null;
        return ($this->recipe_prep_time ?? 0) + ($this->recipe_cook_time ?? 0);
    }

    /** Extrait l'ID YouTube pour construire l'embed */
    public function getYoutubeIdAttribute(): ?string
    {
        if ($this->video_provider !== 'youtube' || ! $this->video_url) {
            return null;
        }
        preg_match(
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/',
            $this->video_url,
            $m
        );
        return $m[1] ?? null;
    }

    // ── Scopes ────────────────────────────────────────────────────
    public function scopePublished($q)
    {
        return $q->where('is_published', true)
                 ->where('published_at', '<=', now());
    }

    public function scopeByCategory($q, string $category)
    {
        return $q->where('category', $category);
    }

    public function scopeFeatured($q)
    {
        return $q->where('is_featured', true);
    }

    // ── Hooks ─────────────────────────────────────────────────────
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! $model->slug) {
                $model->slug = Str::slug($model->title);
            }
            if ($model->is_published && ! $model->published_at) {
                $model->published_at = now();
            }
            // Estimation du temps de lecture (≈ 200 mots/min)
            if ($model->body && ! $model->reading_time) {
                $words = str_word_count(strip_tags($model->body));
                $minutes = max(1, (int) ceil($words / 200));
                $model->reading_time = $minutes . ' min';
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('is_published') && $model->is_published && ! $model->published_at) {
                $model->published_at = now();
            }
        });
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function incrementLikes(): void
    {
        $this->increment('likes');
    }
}