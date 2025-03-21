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

    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('videos.' . $this->video->id)];
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
            'summary' => $this->video->summary
        ];
    }
}
