<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?string $subject = null, array $metadata = []): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'metadata' => $metadata, 'ip_address' => app()->runningInConsole() ? null : request()->ip()]);
    }
}
