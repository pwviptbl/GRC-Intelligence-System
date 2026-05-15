<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'context_snapshot',
        'context_refreshed_at',
        'last_message_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'context_refreshed_at' => 'datetime',
            'last_message_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }
}
