<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class VideoProcessingFailed implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public Video  $video,
        public string $reason
    )
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.' . $this->video->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'video.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->video->id,
            'title' => $this->video->title,
            'status' => $this->video->status,
            'reason' => $this->reason,
        ];
    }
}
