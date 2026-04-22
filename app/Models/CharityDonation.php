<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharityDonation extends Model
{
    protected $table = 'charity_donations';

    protected $fillable = [
        'user_id',
        'product_id',
        'type',
        'amount',
        'quantity',
        'payment_method',
        'status',
        'scratch_card_unlocked',
        'loyalty_points_earned',
        'admin_note',      // ← AJOUTER CETTE LIGNE
        'transaction_id',
        'payment_reference',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'quantity' => 'integer',
        'scratch_card_unlocked' => 'boolean',
        'loyalty_points_earned' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(CharityVoucher::class, 'donation_id');
    }
}