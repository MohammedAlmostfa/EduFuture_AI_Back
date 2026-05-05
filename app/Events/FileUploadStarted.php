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

class FileUploadStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $fileName;
    public int $fileSize;
    public int $userId;
    public string $timestamp;

    public function __construct(string $fileName, int $fileSize, int $userId)
    {
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
        return 'file.upload.started';
    }

    public function broadcastWith(): array
    {
        return [
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'timestamp' => $this->timestamp,
            'message' => "بدأ رفع الملف: {$this->fileName}"
        ];
    }
}
