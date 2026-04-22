<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectiveSubscriptionOrder extends Model
{
    use HasFactory;

    protected $table = 'selective_subscription_orders';

    protected $fillable = [
        'selective_subscription_id',
        'order_id',
        'cycle_number',
        'processed_at',
    ];

    protected $casts = [
        'cycle_number' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function selectiveSubscription(): BelongsTo
    {
        return $this->belongsTo(SelectiveSubscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}