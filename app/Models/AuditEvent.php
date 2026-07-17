<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'source', 'route_name', 'target_type', 'target_id',
        'status_code', 'ip_address', 'user_agent', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
