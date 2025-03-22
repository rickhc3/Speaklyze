<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;

class VideoProcessingProgress implements ShouldBroadcastNow
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public Video $video,
        public int $percent
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("video.progress.{$this->video->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'video_id' => $this->video->id,
            'percent' => $this->percent,
        ];
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }
}
