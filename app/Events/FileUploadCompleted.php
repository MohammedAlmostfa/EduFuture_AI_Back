<?php

namespace App\Events;

use App\Models\File;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FileUploadCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $fileId;
    public string $fileName;
    public int $fileSize;
    public int $userId;
    public string $timestamp;

    public function __construct(int $fileId, string $fileName, int $fileSize, int $userId)
    {
        $this->fileId = $fileId;
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->userId = $userId;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'file.upload.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->fileId,
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'timestamp' => $this->timestamp,
            'message' => "اكتمل رفع الملف: {$this->fileName}"
        ];
    }
}
