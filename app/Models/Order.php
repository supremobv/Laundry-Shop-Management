<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUSES = ['received' => 'Received', 'washing' => 'Washing', 'drying' => 'Drying', 'folding' => 'Folding', 'ready' => 'Ready for pickup', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasManyThrough(Refund::class, Payment::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function getPaidCentsAttribute(): int
    {
        $paid = array_key_exists('payments_sum_amount_cents', $this->attributes)
            ? (int) $this->attributes['payments_sum_amount_cents'] : (int) $this->payments->sum('amount_cents');
        $refunded = array_key_exists('refunds_sum_amount_cents', $this->attributes)
            ? (int) $this->attributes['refunds_sum_amount_cents'] : (int) $this->refunds->sum('amount_cents');

        return $paid - $refunded;
    }

    public function getBalanceCentsAttribute(): int
    {
        return $this->status === 'cancelled' ? 0 : max(0, $this->total_cents - $this->paid_cents);
    }

    public function getOverdueAttribute(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled', 'ready']) && $this->due_at->isPast();
    }
}
