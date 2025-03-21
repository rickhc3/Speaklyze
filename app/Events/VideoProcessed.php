<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class VideoProcessed implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public Video $video;
    public int $userId; // Adicionando o ID do usuário

    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->userId = $video->user_id; // Pegando o ID do usuário que enviou o vídeo
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->userId}")]; // Envia apenas para o usuário dono do vídeo
    }

    public function broadcastAs(): string
    {
        return 'video.processed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->video->id,
            'title' => $this->video->title,
            'summary' => $this->video->summary,
            'status' => $this->video->status,
            'video_id' => $this->video->video_id,
        ];
    }
}
