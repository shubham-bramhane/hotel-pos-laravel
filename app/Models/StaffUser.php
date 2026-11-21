<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffUser extends Model
{
    protected $fillable = ['name', 'role', 'pin_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function homeRoute(): string
    {
        return match ($this->role) {
            'owner' => route('owner.dashboard'),
            'cashier' => route('counter.index'),
            'kitchen' => route('kitchen.index'),
            default => route('waiter.index'),
        };
    }

    public function canAccess(string ...$roles): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        return in_array($this->role, $roles, true);
    }
}
