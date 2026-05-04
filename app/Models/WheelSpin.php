<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelSpin extends Model
{
    protected $fillable = [
        'user_id',
        'wheel_config_id',
        'month_year',
        'spin_number',
        'prize_label',
        'prize_type',
        'prize_value',
        'prize_claimed',
        'prize_claimed_at',
        'triggered_by',
        'trigger_order_id',
    ];

    protected $casts = [
        'prize_claimed'    => 'boolean',
        'prize_claimed_at' => 'datetime',
        'prize_value'      => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wheelConfig(): BelongsTo
    {
        return $this->belongsTo(WheelConfig::class);
    }
}