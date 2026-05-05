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

class FileProcessingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public File $file;
    public string $timestamp;

    public function __construct(File $file)
    {
        $this->file = $file;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * القناة التي سيتم البث عليها
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->file->user_id}"),
            new Channel("files.processing")
        ];
    }

    /**
     * اسم الـ Event للـ Broadcasting
     */
    public function broadcastAs(): string
    {
        return 'file.processing.started';
    }

    /**
     * البيانات التي سيتم بثها
     */
    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'file_size' => $this->file->size,
            'status' => 'processing',
            'timestamp' => $this->timestamp,
            'message' => "بدأت معالجة الملف: {$this->file->name}"
        ];
    }
}
