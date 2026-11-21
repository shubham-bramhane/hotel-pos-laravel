<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['staff_id', 'staff_name', 'staff_role', 'action', 'entity_type', 'entity_id', 'details', 'ip_address'];

    protected $casts = ['details' => 'array'];
}
