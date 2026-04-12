<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'category_id','partner_id','name','name_en','slug','sku','barcode',
        'description','description_en','price','compare_price','cost_price',
        'weight','unit','brand','origin','stock','low_stock_threshold','track_stock',
        'is_bio','is_local','is_eco','is_vegan','is_draft','is_gluten_free','is_premium',
        'is_new','is_featured','is_bestseller','admin_label','admin_label_discount',
        'is_active','expiry_date','expiry_alert','meta_title','meta_description',
    ];
    
    protected $casts = [
        'price'=>'float','compare_price'=>'float','cost_price'=>'float',
        'is_bio'=>'boolean','is_local'=>'boolean','is_eco'=>'boolean','is_vegan'=>'boolean',
        'is_gluten_free'=>'boolean','is_premium'=>'boolean','is_new'=>'boolean','is_draft' => 'boolean',
        'is_featured'=>'boolean','is_bestseller'=>'boolean','is_active'=>'boolean',
        'track_stock'=>'boolean','expiry_date'=>'date',
    ];
    
    protected $appends = ['discount_percentage','in_stock','is_low_stock','primary_image_url'];

    // ==================== RELATIONSHIPS ====================
    
    public function category()     { return $this->belongsTo(Category::class); }
    public function partner()      { return $this->belongsTo(Partner::class); }
    public function images()       { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function primaryImage() { return $this->hasOne(ProductImage::class)->where('is_primary', true); }
    public function reviews()      { return $this->hasMany(Review::class)->where('is_approved', true); }
    public function sizeOptions()  { return $this->hasMany(ProductSizeOption::class); }

    // ==================== SCOPES ====================
    
    public function scopeActive($q)    { return $q->where('is_active', true); }
    public function scopeFeatured($q)  { return $q->where('is_featured', true); }
    public function scopeNew($q)       { return $q->where('is_new', true); }
    public function scopePremium($q)   { return $q->where('is_premium', true); }
    public function scopeLowStock($q)  { return $q->whereRaw('stock <= low_stock_threshold')->where('track_stock', true)->where('stock', '>', 0); }
    public function scopeOutOfStock($q){ return $q->where('stock', 0)->where('track_stock', true); }
    
    /**
     * Scope pour les produits qui expirent bientôt (dans les X jours)
     */
    public function scopeExpiringSoon($q, $days = 30)
    {
        return $q->whereNotNull('expiry_date')
                 ->where('expiry_date', '<=', Carbon::now()->addDays($days))
                 ->where('expiry_date', '>=', Carbon::now());
    }
    
    /**
     * Scope pour les produits déjà expirés
     */
    public function scopeExpired($q)
    {
        return $q->whereNotNull('expiry_date')
                 ->where('expiry_date', '<', Carbon::now());
    }
    
    /**
     * Scope pour les produits avec alerte d'expiration activée
     */
    public function scopeExpiryAlertEnabled($q)
    {
        return $q->where('expiry_alert', true);
    }

    // ==================== ACCESSORS ====================
    
    public function getDiscountPercentageAttribute(): ?int {
        if (!$this->compare_price || $this->compare_price <= $this->price) return null;
        return (int)round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }
    
    public function getInStockAttribute(): bool    { 
        return !$this->track_stock || $this->stock > 0; 
    }
    
    public function getIsLowStockAttribute(): bool { 
        return $this->track_stock && $this->stock <= $this->low_stock_threshold && $this->stock > 0; 
    }
    
    public function getPrimaryImageUrlAttribute(): ?string {
        return $this->primaryImage ? asset('storage/' . $this->primaryImage->path) : null;
    }
    
    /**
     * Vérifier si le produit expire bientôt
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date <= Carbon::now()->addDays(30) && $this->expiry_date >= Carbon::now();
    }
    
    /**
     * Vérifier si le produit est expiré
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date < Carbon::now();
    }
    
    /**
     * Jours restants avant expiration
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        if ($this->expiry_date < Carbon::now()) return null;
        return Carbon::now()->diffInDays($this->expiry_date);
    }

    // ==================== METHODS ====================
    
    public function decrementStock(int $qty): void {
        if ($this->track_stock) {
            $this->decrement('stock', $qty);
            $this->refresh();
            if ($this->stock <= 0) $this->update(['admin_label' => 'stock_epuise']);
        }
    }
    
    /**
     * Récupérer les produits qui expirent bientôt (statique)
     */
    public static function getExpiringSoon($limit = 10)
    {
        return self::expiringSoon()->limit($limit)->get();
    }
    
    /**
     * Récupérer les produits expirés
     */
    public static function getExpired($limit = 10)
    {
        return self::expired()->limit($limit)->get();
    }
    
    /**
     * Obtenir toutes les alertes pour le dashboard
     */
    public static function getAlerts(): array
    {
        return [
            'low_stock' => [
                'count' => self::lowStock()->count(),
                'items' => self::lowStock()->limit(5)->get(),
                'threshold' => 'low_stock_threshold',
            ],
            'out_of_stock' => [
                'count' => self::outOfStock()->count(),
                'items' => self::outOfStock()->limit(5)->get(),
            ],
            'expiring_soon' => [
                'count' => self::expiringSoon()->count(),
                'items' => self::getExpiringSoon(5),
                'days' => 30,
            ],
            'expired' => [
                'count' => self::expired()->count(),
                'items' => self::getExpired(5),
            ],
        ];
    }
    
    /**
     * Mettre à jour l'admin_label basé sur l'état du stock
     */
    public function updateAdminLabelFromStock(): void
    {
        if (!$this->track_stock) return;
        
        if ($this->stock <= 0) {
            $this->update(['admin_label' => 'stock_epuise']);
        } elseif ($this->is_low_stock) {
            $this->update(['admin_label' => 'stock_limite']);
        }
    }
}