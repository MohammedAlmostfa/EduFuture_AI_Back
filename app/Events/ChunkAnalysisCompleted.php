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

class ChunkAnalysisCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public File $file;
    public int $chunkIndex;
    public int $totalChunks;
    public float $progress;
    public string $timestamp;

    public function __construct(File $file, int $chunkIndex, int $totalChunks)
    {
        $this->file = $file;
        $this->chunkIndex = $chunkIndex + 1; // 1-indexed للعرض
        $this->totalChunks = $totalChunks;
        $this->progress = ($this->chunkIndex / $totalChunks) * 100;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->file->user_id}"),
            new Channel("files.{$this->file->id}.progress")
        ];
    }

    public function broadcastAs(): string
    {
        return 'chunk.analysis.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->file->id,
            'chunk_index' => $this->chunkIndex,
            'total_chunks' => $this->totalChunks,
            'progress' => round($this->progress, 2),
            'timestamp' => $this->timestamp,
            'message' => "تم تحليل جزء {$this->chunkIndex} من {$this->totalChunks}"
        ];
    }
}
