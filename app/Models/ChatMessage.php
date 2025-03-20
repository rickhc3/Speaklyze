<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'chat_session_id', 'role', 'message'];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
