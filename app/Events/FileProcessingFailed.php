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

class FileProcessingFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public File $file;
    public string $errorMessage;
    public string $timestamp;
    public int $attempt;

    public function __construct(File $file, Throwable $exception, int $attempt = 1)
    {
        $this->file = $file;
        $this->errorMessage = $exception->getMessage();
        $this->timestamp = now()->toIso8601String();
        $this->attempt = $attempt;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->file->user_id}"),
            new Channel("files.failed")
        ];
    }

    public function broadcastAs(): string
    {
        return 'file.processing.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'status' => 'failed',
            'error' => $this->errorMessage,
            'attempt' => $this->attempt,
            'timestamp' => $this->timestamp,
            'message' => "فشلت معالجة الملف: {$this->file->name}"
        ];
    }
}
