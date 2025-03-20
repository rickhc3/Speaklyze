<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'youtube_url',
        'video_path',
        'audio_path',
        'transcription',
        'summary',
        'video_id',
        'chat_session_id',	
    ];

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }
}
