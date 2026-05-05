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

class FileProcessingCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public File $file;
    public array $statistics;
    public string $timestamp;

    public function __construct(File $file)
    {
        $this->file = $file;
        $this->timestamp = now()->toIso8601String();

        // إحصائيات المعالجة
        $this->statistics = [
            'chunks_processed' => $file->chunks_processed,
            'chunks_total' => $file->chunks_total,
            'success_rate' => $file->chunks_total > 0
                ? round(($file->chunks_processed / $file->chunks_total) * 100, 2)
                : 0,
            'processing_time' => $file->updated_at->diffInSeconds($file->created_at),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->file->user_id}"),
            new Channel("files.completed")
        ];
    }

    public function broadcastAs(): string
    {
        return 'file.processing.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'status' => 'completed',
            'statistics' => $this->statistics,
            'timestamp' => $this->timestamp,
            'message' => "اكتملت معالجة الملف: {$this->file->name}"
        ];
    }
}
