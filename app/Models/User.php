<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public const PERMISSIONS = [
        'admin' => ['orders.view', 'orders.create', 'orders.process', 'orders.cancel', 'payments.create', 'payments.refund', 'customers.manage', 'catalog.manage', 'inventory.manage', 'expenses.manage', 'reports.view', 'staff.manage', 'settings.manage', 'audit.view'],
        'manager' => ['orders.view', 'orders.create', 'orders.process', 'orders.cancel', 'payments.create', 'payments.refund', 'customers.manage', 'catalog.manage', 'inventory.manage', 'expenses.manage', 'reports.view'],
        'cashier' => ['orders.view', 'orders.create', 'orders.process', 'payments.create', 'customers.manage'],
        'operator' => ['orders.view', 'orders.process', 'inventory.manage'],
    ];

    public function hasPermission(string $permission): bool
    {
        return $this->active && in_array($permission, self::PERMISSIONS[$this->role] ?? [], true);
    }
}
