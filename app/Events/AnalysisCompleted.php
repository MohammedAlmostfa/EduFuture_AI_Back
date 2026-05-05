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

class AnalysisCompleted
{
    use Dispatchable, SerializesModels;

    public array $analysis;
    public array $context;

    public function __construct(array $analysis, array $context = [])
    {
        $this->analysis = $analysis;
        $this->context = $context;
    }
}
